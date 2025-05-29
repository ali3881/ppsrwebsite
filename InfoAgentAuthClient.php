<?php

/**
 * Custom exception for authentication failures.
 * Used when the InfoAgentAuthClient fails to obtain or validate an access token.
 */
class AuthenticationException extends \Exception {}

/**
 * A client for handling OAuth2 client credentials authentication with the InfoAgent API.
 * It fetches and caches an access token, automatically renewing it when it expires.
 */
class InfoAgentAuthClient
{
    /**
     * The client ID for OAuth2 authentication.
     * @var string
     */
    private $clientId;

    /**
     * The client secret for OAuth2 authentication.
     * @var string
     */
    private $clientSecret;

    /**
     * The token endpoint URL.
     * @var string
     */
    private $tokenUrl;

    /**
     * The current access token. Null if no token has been fetched yet.
     * @var string|null
     */
    private $accessToken;

    /**
     * Timestamp (seconds since epoch) when the current access token expires.
     * Zero if no token has been fetched.
     * @var int
     */
    private $tokenExpiry;

    /**
     * Default token URL if not provided during instantiation.
     * @var string
     */
    private const DEFAULT_TOKEN_URL = 'https://api.dev.infoagent.com.au/oauth/token';

    /**
     * User-Agent string sent with HTTP requests to the token endpoint.
     * @var string
     */
    private const USER_AGENT = 'MyWebsiteIntegration-PHP/1.0';

    /**
     * Buffer in seconds. A token is considered expired if it's due to expire
     * within this buffer period, prompting a preemptive refresh.
     * @var int
     */
    private const TOKEN_EXPIRY_BUFFER = 30;

    /**
     * Initializes the InfoAgentAuthClient.
     *
     * @param string $clientId The client ID for OAuth2 authentication.
     * @param string $clientSecret The client secret for OAuth2 authentication.
     * @param string|null $tokenUrl The token endpoint URL. If null, defaults to DEFAULT_TOKEN_URL.
     */
    public function __construct(string $clientId, string $clientSecret, ?string $tokenUrl = null)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->tokenUrl = $tokenUrl ?: self::DEFAULT_TOKEN_URL;
        $this->accessToken = null;
        $this->tokenExpiry = 0;
    }

    /**
     * Checks if the current access token is valid and not expired.
     *
     * @return bool True if the token exists and is not expired (considering a buffer), False otherwise.
     */
    private function isTokenValid(): bool
    {
        return $this->accessToken !== null && $this->tokenExpiry > (time() + self::TOKEN_EXPIRY_BUFFER);
    }

    /**
     * Fetches a new access token from the token endpoint.
     * This method handles the cURL request, response parsing, and error checking.
     * On success, it updates the internal accessToken and tokenExpiry properties.
     *
     * @return bool True on successful token fetch and update. (Nominal, as exceptions are primary for failure).
     * @throws AuthenticationException If token retrieval fails due to cURL errors, HTTP errors,
     *                                 invalid JSON response, or missing token data in response.
     */
    private function fetchNewAccessToken(): bool
    {
        $ch = curl_init();
        if ($ch === false) {
            throw new AuthenticationException('Failed to initialize cURL session.');
        }

        $postData = http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        curl_setopt($ch, CURLOPT_URL, $this->tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: ' . self::USER_AGENT,
        ]);
        // It's good practice to set a timeout
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10 seconds timeout for connection
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // 5 seconds timeout for connection phase


        $response = curl_exec($ch);

        if ($response === false) {
            $curlErrno = curl_errno($ch);
            $curlError = curl_error($ch);
            curl_close($ch);
            throw new AuthenticationException(
                sprintf('cURL error during token request: [%d] %s', $curlErrno, $curlError)
            );
        }

        $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpStatusCode !== 200) {
            throw new AuthenticationException(
                sprintf(
                    'Failed to retrieve access token. HTTP Status: %d. Response: %s',
                    $httpStatusCode,
                    $response // Include response body for debugging if possible
                )
            );
        }

        $decodedResponse = json_decode($response);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new AuthenticationException(
                sprintf('Failed to parse JSON response from token endpoint. Error: %s. Response: %s', json_last_error_msg(), $response)
            );
        }

        if (!isset($decodedResponse->access_token) || !isset($decodedResponse->expires_in)) {
            throw new AuthenticationException(
                sprintf('Invalid token response format. Missing access_token or expires_in. Response: %s', $response)
            );
        }
        
        if (empty($decodedResponse->access_token)) {
            throw new AuthenticationException(
                 sprintf('Received an empty access_token. Response: %s', $response)
            );
        }
        
        if (!is_numeric($decodedResponse->expires_in) || $decodedResponse->expires_in <=0) {
             throw new AuthenticationException(
                 sprintf('Invalid expires_in value. Must be a positive number. Received: %s. Response: %s', $decodedResponse->expires_in, $response)
            );
        }


        $this->accessToken = $decodedResponse->access_token;
        $this->tokenExpiry = time() + ((int)$decodedResponse->expires_in);

        return true;
    }

    /**
     * Retrieves an access token, fetching a new one if necessary.
     *
     * @return string The access token.
     * @throws AuthenticationException If token retrieval fails.
     */
    public function getAccessToken(): string
    {
        if ($this->isTokenValid()) {
            return $this->accessToken;
        }

        // Try to fetch a new token. fetchNewAccessToken will throw on failure.
        $this->fetchNewAccessToken();
        
        // After a successful fetch, accessToken must be non-null.
        // If fetchNewAccessToken somehow returned true but accessToken is null (should not happen with current logic),
        // it indicates a logical flaw.
        if ($this->accessToken === null) {
             throw new AuthenticationException('Failed to obtain a valid access token after fetch attempt.');
        }

        return $this->accessToken;
    }
}

// Example Usage (Conceptual - requires a running token endpoint to actually work)
/*
try {
    // Replace with your actual credentials or load from config
    // IMPORTANT: Do not commit real client_id and client_secret to version control.
    $clientId = 'YOUR_CLIENT_ID';
    $clientSecret = 'YOUR_CLIENT_SECRET';
    // Optional: if your token URL is different from the default
    // $tokenUrl = 'YOUR_CUSTOM_TOKEN_URL';

    echo "Attempting to initialize client...\n";
    // $authClient = new InfoAgentAuthClient($clientId, $clientSecret, $tokenUrl ?? null);
    $authClient = new InfoAgentAuthClient($clientId, $clientSecret);


    echo "Attempting to get access token (first call)...\n";
    $token1 = $authClient->getAccessToken();
    echo "Token 1 obtained: " . substr($token1, 0, 20) . "...\n";

    echo "Attempting to get access token (second call - should be cached)...\n";
    $token2 = $authClient->getAccessToken();
    echo "Token 2 obtained: " . substr($token2, 0, 20) . "...\n";

    if ($token1 === $token2) {
        echo "Token 1 and Token 2 are the same (cached successfully).\n";
    } else {
        echo "Error: Token 1 and Token 2 are different, caching failed or token expired too quickly.\n";
    }

    // To test token expiry and re-fetch, you would need to:
    // 1. Get a token
    // 2. Wait for longer than its 'expires_in' duration (minus buffer)
    // 3. Call getAccessToken() again and verify it's a new token.
    // This is hard to automate in a simple script run without actual long waits or
    // by manipulating the client's internal state for testing (which isn't ideal for example code).

} catch (AuthenticationException $e) {
    echo "Authentication Error: " . $e->getMessage() . "\n";
    // In a real app, log this error, notify admins, etc.
} catch (\Exception $e) {
    echo "An unexpected error occurred: " . $e->getMessage() . "\n";
}
*/

?>

<?php

require_once __DIR__ . '/InfoAgentAuthClient.php'; // Assuming InfoAgentAuthClient.php is in the same directory

/**
 * Custom exception for Vehicle Report API related failures.
 * Used when the VehicleReportClient encounters issues interacting with the InfoAgent API,
 * including authentication problems passed up from InfoAgentAuthClient.
 */
class ReportApiException extends \Exception {}

/**
 * A client for interacting with the InfoAgent Vehicle Report and PPSR Certificate APIs.
 * This client handles making requests to generate vehicle reports and retrieve PPSR certificates,
 * using an InfoAgentAuthClient instance for authentication.
 */
class VehicleReportClient
{
    /**
     * Instance of InfoAgentAuthClient used for obtaining access tokens.
     * @var InfoAgentAuthClient
     */
    private $authClient;

    /**
     * Base URL for the InfoAgent vehicle report API.
     * @var string
     */
    private $baseApiUrl;

    /**
     * User-Agent string sent with HTTP requests to the vehicle report API.
     * @var string
     */
    private $userAgent;

    /**
     * Default base API URL if not provided during instantiation.
     * @var string
     */
    private const DEFAULT_BASE_API_URL = 'https://api.dev.infoagent.com.au/open-api/v1/ivds/au';

    /**
     * Default User-Agent string if not provided during instantiation.
     * @var string
     */
    private const DEFAULT_USER_AGENT = 'MyWebsiteIntegration-PHP/1.0';

    /**
     * Allowed search types for PPSR reports. Used for input validation.
     * @var string[]
     */
    private const ALLOWED_SEARCH_TYPES = ['vin', 'plateAndState', 'chassisAndMake'];

    /**
     * Initializes the VehicleReportClient.
     *
     * @param InfoAgentAuthClient $authClient An initialized instance of InfoAgentAuthClient for handling authentication.
     * @param string|null $baseApiUrl The base URL for the vehicle report API. If null, defaults to DEFAULT_BASE_API_URL.
     * @param string|null $userAgent The User-Agent string for HTTP requests. If null, defaults to DEFAULT_USER_AGENT.
     */
    public function __construct(InfoAgentAuthClient $authClient, ?string $baseApiUrl = null, ?string $userAgent = null)
    {
        $this->authClient = $authClient;
        $this->baseApiUrl = $baseApiUrl ?: self::DEFAULT_BASE_API_URL;
        $this->userAgent = $userAgent ?: self::DEFAULT_USER_AGENT;
    }

    /**
     * Performs an HTTP request using cURL.
     *
     * @param string $url The full URL to request.
     * @param string $method The HTTP method (e.g., 'GET', 'POST').
     * @param array $headers An array of HTTP header strings.
     * @param string|null $data The request body data (typically for 'POST' or 'PUT' requests).
     * @return array An associative array containing:
     *               'statusCode' (int) - The HTTP status code.
     *               'body' (string|false) - The response body, or false on cURL execution failure.
     *               'curlErrno' (int) - The cURL error number, 0 if no error.
     *               'curlError' (string) - The cURL error message, empty if no error.
     * @throws ReportApiException If the cURL session cannot be initialized.
     */
    private function _doRequest(string $url, string $method, array $headers = [], ?string $data = null): array
    {
        $ch = curl_init();
        if ($ch === false) {
            throw new ReportApiException('Failed to initialize cURL session.');
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent); // Set User-Agent from class property
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 seconds request timeout
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 10 seconds connection timeout

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
        } elseif (strtoupper($method) !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
        }

        $responseBody = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return [
            'statusCode' => $httpStatusCode,
            'body' => $responseBody,
            'curlErrno' => $curlErrno,
            'curlError' => $curlError,
        ];
    }

    /**
     * Generates a PPSR Report by calling the InfoAgent API.
     *
     * @param string $searchType The type of vehicle identifier.
     *                           Expected values: "vin", "plateAndState", "chassisAndMake".
     * @param array $searchParameters Associative array of search parameters.
     *                                Examples:
     *                                - For "vin": `['vin' => 'YOUR_VIN_HERE']`
     *                                - For "plateAndState": `['plateNumber' => 'ABC123', 'state' => 'NSW']`
     *                                - For "chassisAndMake": `['chassisNumber' => '123XYZ', 'make' => 'Toyota']`
     * @return array The decoded JSON response from the API as an associative array.
     * @throws ReportApiException If report generation fails due to authentication issues,
     *                            API errors, network problems, or invalid JSON response.
     * @throws \InvalidArgumentException If `$searchType` is not one of the allowed values or if
     *                                   `$searchParameters` are not in the expected format for the client.
     */
    public function generatePpsrReport(string $searchType, array $searchParameters): array
    {
        if (!in_array($searchType, self::ALLOWED_SEARCH_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid searchType: {$searchType}. Allowed types are: " . implode(', ', self::ALLOWED_SEARCH_TYPES));
        }

        try {
            $accessToken = $this->authClient->getAccessToken();
        } catch (AuthenticationException $e) {
            throw new ReportApiException('Authentication failed: ' . $e->getMessage(), $e->getCode(), $e);
        }

        $url = $this->baseApiUrl . '/ppsr-reports';

        // Construct the request body carefully
        $requestBody = [
            'searchParameters' => []
        ];
        // The API spec implies searchParameters contains a key that IS the searchType,
        // and its value are the parameters for that type.
        $requestBody['searchParameters'][$searchType] = $searchParameters;


        $jsonBody = json_encode($requestBody);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ReportApiException('Failed to encode request body as JSON: ' . json_last_error_msg());
        }

        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $response = $this->_doRequest($url, 'POST', $headers, $jsonBody);

        if ($response['curlErrno'] !== 0) {
            throw new ReportApiException(
                sprintf('cURL error during PPSR report generation: [%d] %s', $response['curlErrno'], $response['curlError'])
            );
        }

        if ($response['statusCode'] !== 200 && $response['statusCode'] !== 201) {
            throw new ReportApiException(
                sprintf(
                    'Failed to generate PPSR report. HTTP Status: %d. Response: %s',
                    $response['statusCode'],
                    $response['body']
                )
            );
        }

        $decodedResponse = json_decode($response['body'], true); // true for associative array

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ReportApiException(
                sprintf('Failed to parse JSON response from PPSR report API. Error: %s. Response: %s', json_last_error_msg(), $response['body'])
            );
        }

        return $decodedResponse;
    }

    /**
     * Retrieves a PPSR certificate by its ID from the InfoAgent API.
     *
     * @param string $certificateId The unique identifier for the PPSR certificate.
     * @return string The raw binary content of the PPSR certificate (assumed to be PDF).
     * @throws ReportApiException If certificate retrieval fails due to authentication issues,
     *                            API errors (e.g., certificate not found), or network problems.
     * @throws \InvalidArgumentException If `$certificateId` is empty.
     */
    public function getPpsrCertificate(string $certificateId): string
    {
        if (empty($certificateId)) {
             throw new \InvalidArgumentException("Certificate ID cannot be empty.");
        }

        try {
            $accessToken = $this->authClient->getAccessToken();
        } catch (AuthenticationException $e) {
            throw new ReportApiException('Authentication failed: ' . $e->getMessage(), $e->getCode(), $e);
        }

        $url = $this->baseApiUrl . '/ppsr-certificates/' . $certificateId;

        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/pdf', // Or 'application/octet-stream'
        ];

        $response = $this->_doRequest($url, 'GET', $headers);

        if ($response['curlErrno'] !== 0) {
            throw new ReportApiException(
                sprintf('cURL error during PPSR certificate retrieval: [%d] %s', $response['curlErrno'], $response['curlError'])
            );
        }

        if ($response['statusCode'] !== 200) {
            throw new ReportApiException(
                sprintf(
                    'Failed to retrieve PPSR certificate. HTTP Status: %d. Response: %s',
                    $response['statusCode'],
                    $response['body'] // Body might contain error details
                )
            );
        }
        
        if ($response['body'] === null || $response['body'] === '') {
             // This might happen if CURLOPT_RETURNTRANSFER was false, but _doRequest ensures it's true.
             // Or if the server legitimately returns an empty body for a 200, which would be unusual for a PDF.
            throw new ReportApiException(
                sprintf('Received empty body for PPSR certificate. HTTP Status: %d.', $response['statusCode'])
            );
        }

        return $response['body'];
    }
}

// Example Usage (Conceptual - requires a running InfoAgentAuthClient and API endpoint)
/*
try {
    // Assume $authClient is an initialized InfoAgentAuthClient instance
    // $authClientId = 'YOUR_AUTH_CLIENT_ID';
    // $authClientSecret = 'YOUR_AUTH_CLIENT_SECRET';
    // $authClient = new InfoAgentAuthClient($authClientId, $authClientSecret);

    // $reportClient = new VehicleReportClient($authClient);

    // Example: Generate PPSR Report by VIN
    // echo "Generating PPSR report by VIN...\n";
    // $vin = 'TESTVIN1234567890';
    // $reportData = $reportClient->generatePpsrReport('vin', ['vin' => $vin]); // Note: API spec implies value of 'vin' is the VIN itself
    // print_r($reportData);

    // Example: Generate PPSR Report by Plate and State
    // echo "\nGenerating PPSR report by Plate & State...\n";
    // $plateReportData = $reportClient->generatePpsrReport('plateAndState', [
    //     'plateNumber' => 'ABC123',
    //     'state' => 'NSW'
    // ]);
    // print_r($plateReportData);

    // Example: Retrieve PPSR Certificate (assuming you have a certificate ID from a report)
    // if (isset($reportData['certificateDetails']['certificateId'])) { // Adjust path based on actual response structure
    //     $certificateId = $reportData['certificateDetails']['certificateId'];
    //     echo "\nRetrieving PPSR certificate with ID: {$certificateId}...\n";
    //     $certificateContent = $reportClient->getPpsrCertificate($certificateId);
    //     // Save or process $certificateContent (e.g., file_put_contents("certificate_{$certificateId}.pdf", $certificateContent));
    //     echo "Certificate content retrieved (length: " . strlen($certificateContent) . " bytes).\n";
    // } else {
    //     // echo "\nNo certificate ID found in report to retrieve.\n";
    // }

} catch (ReportApiException $e) {
    echo "Report API Error: " . $e->getMessage() . "\n";
    if ($e->getPrevious()) {
        echo "Caused by: " . $e->getPrevious()->getMessage() . "\n";
    }
    // Log this error, notify admins, show user-friendly message
} catch (InvalidArgumentException $e) {
    echo "Invalid Argument Error: " . $e->getMessage() . "\n";
} catch (\Exception $e) {
    echo "An unexpected error occurred: " . $e->getMessage() . "\n";
}
*/

?>

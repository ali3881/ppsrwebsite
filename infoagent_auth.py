import requests
import time

# Custom exception for authentication errors
class AuthenticationError(Exception):
    """Custom exception for authentication failures."""
    pass

class InfoAgentAuthClient:
    """
    A client for handling OAuth2 authentication with the InfoAgent API.
    """
    DEFAULT_TOKEN_URL = "https://api.dev.infoagent.com.au/oauth/token"

    def __init__(self, client_id: str, client_secret: str, token_url: str = None):
        """
        Initializes the InfoAgentAuthClient.

        Args:
            client_id: The client ID for OAuth2 authentication.
            client_secret: The client secret for OAuth2 authentication.
            token_url: The token endpoint URL. Defaults to DEFAULT_TOKEN_URL.
        """
        self.client_id = client_id
        self.client_secret = client_secret
        self.token_url = token_url or self.DEFAULT_TOKEN_URL
        self.access_token: str | None = None
        self.token_expiry: float | None = None  # Stores expiry time as a timestamp

    def _is_token_valid(self) -> bool:
        """
        Checks if the current access token is valid and not expired.

        Returns:
            True if the token exists and is not expired (with a 5-second buffer), False otherwise.
        """
        if not self.access_token or not self.token_expiry:
            return False
        # Check if token expires in the next 5 seconds or has already expired
        return time.time() < (self.token_expiry - 5) # 5-second buffer

    def get_access_token(self) -> str:
        """
        Retrieves an access token, fetching a new one if necessary.

        Returns:
            The access token.

        Raises:
            AuthenticationError: If token retrieval fails.
        """
        if self._is_token_valid() and self.access_token:
            return self.access_token

        headers = {
            "Content-Type": "application/x-www-form-urlencoded",
            "User-Agent": "MyWebsiteIntegration/1.0",
        }
        data = {
            "grant_type": "client_credentials",
            "client_id": self.client_id,
            "client_secret": self.client_secret,
        }

        try:
            response = requests.post(self.token_url, headers=headers, data=data, timeout=10) # 10s timeout
            response.raise_for_status()  # Raises HTTPError for bad responses (4XX or 5XX)
        except requests.exceptions.Timeout:
            raise AuthenticationError(f"Request to {self.token_url} timed out.")
        except requests.exceptions.HTTPError as e:
            # Attempt to get more details from the response if possible
            error_detail = "No additional error detail from server."
            try:
                error_detail = response.json()
            except requests.exceptions.JSONDecodeError:
                error_detail = response.text
            raise AuthenticationError(
                f"Failed to retrieve access token from {self.token_url}. "
                f"Status: {response.status_code}. Response: {error_detail}. Original error: {e}"
            )
        except requests.exceptions.RequestException as e:
            raise AuthenticationError(f"An unexpected error occurred during token retrieval: {e}")

        try:
            token_data = response.json()
        except requests.exceptions.JSONDecodeError:
            raise AuthenticationError(
                f"Failed to parse JSON response from token endpoint. Response text: {response.text}"
            )

        if "access_token" not in token_data or "expires_in" not in token_data:
            raise AuthenticationError(
                f"Invalid token response format. 'access_token' or 'expires_in' missing. Response: {token_data}"
            )

        self.access_token = token_data["access_token"]
        expires_in = token_data["expires_in"]

        if not isinstance(expires_in, (int, float)) or expires_in <= 0:
            raise AuthenticationError(
                f"'expires_in' must be a positive number. Received: {expires_in}"
            )

        self.token_expiry = time.time() + expires_in
        
        if not self.access_token: # Should be caught by "access_token" not in token_data, but as a safeguard
             raise AuthenticationError("Received an empty access_token.")

        return self.access_token

if __name__ == '__main__':
    # This is a basic example of how to use the client.
    # In a real application, you would replace these with actual credentials and error handling.
    print("Attempting to create InfoAgentAuthClient (example usage - will not make real calls here)")
    
    # Example Usage (Illustrative - requires a running token endpoint to actually work)
    # IMPORTANT: Do not commit real client_id and client_secret to version control.
    # Use environment variables or a configuration management system in a real application.
    try:
        # MOCK_CLIENT_ID = "your_client_id"
        # MOCK_CLIENT_SECRET = "your_client_secret"
        # MOCK_TOKEN_URL = "http://localhost:8000/oauth/token" # Example: a local mock server

        # print(f"Attempting to initialize client with mock URL: {MOCK_TOKEN_URL}")
        # client = InfoAgentAuthClient(MOCK_CLIENT_ID, MOCK_CLIENT_SECRET, token_url=MOCK_TOKEN_URL)
        
        # print("Attempting to get access token...")
        # This part would make a real HTTP request if uncommented and a server was available.
        # token = client.get_access_token()
        # print(f"Successfully obtained token (first call): {token[:20]}...") # Print first 20 chars for brevity
        # print(f"Token expiry timestamp: {client.token_expiry}")

        # # Call again to test cached token
        # token2 = client.get_access_token()
        # print(f"Successfully obtained token (second call, should be cached): {token2[:20]}...")
        
        # # Example of how token expiry might be handled
        # if client.token_expiry:
        #     print(f"Token is valid for approximately {client.token_expiry - time.time():.0f} more seconds.")
        #     # Simulate token expiry for testing _is_token_valid
        #     # print("Simulating token expiry...")
        #     # client.token_expiry = time.time() - 3600 # Set expiry to one hour ago
        #     # print(f"Is token valid after simulation? {client._is_token_valid()}")
        #     # token3 = client.get_access_token() # This would fetch a new token
        #     # print(f"Fetched new token after simulated expiry: {token3[:20]}...")

    except AuthenticationError as e:
        print(f"Authentication Error: {e}")
    except Exception as e:
        print(f"An unexpected error occurred: {e}")

    print("\nInfoAgentAuthClient structure created.")
    print("To fully test, uncomment the example usage and point to a valid OAuth2 token endpoint.")

    # A simple test case for the default URL
    client_default_url = InfoAgentAuthClient("test_id", "test_secret")
    assert client_default_url.token_url == InfoAgentAuthClient.DEFAULT_TOKEN_URL
    print(f"\nClient initialized with default token URL: {client_default_url.token_url}")

    # A simple test case for a custom URL
    custom_url = "https://my.custom.url/token"
    client_custom_url = InfoAgentAuthClient("test_id", "test_secret", token_url=custom_url)
    assert client_custom_url.token_url == custom_url
    print(f"Client initialized with custom token URL: {client_custom_url.token_url}")
    print("\nBasic __init__ tests passed.")
    print("Module infoagent_auth.py created successfully with InfoAgentAuthClient class.")

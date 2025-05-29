import requests
from infoagent_auth import InfoAgentAuthClient, AuthenticationError

# Custom exception for report generation errors
class ReportGenerationError(Exception):
    """Custom exception for vehicle report generation failures."""
    pass

# Custom exception for certificate retrieval errors
class CertificateRetrievalError(ReportGenerationError):
    """Custom exception for PPSR certificate retrieval failures."""
    pass

class VehicleReportClient:
    """
    A client for generating vehicle reports using the InfoAgent API.
    """
    DEFAULT_BASE_API_URL = "https://api.dev.infoagent.com.au/open-api/v1/ivds/au"
    DEFAULT_USER_AGENT = "MyWebsiteIntegration/1.0" # Default User-Agent

    def __init__(self, auth_client: InfoAgentAuthClient, base_api_url: str = None, user_agent: str = None):
        """
        Initializes the VehicleReportClient.

        Args:
            auth_client: An instance of InfoAgentAuthClient for handling authentication.
            base_api_url: The base URL for the vehicle report API.
                          Defaults to DEFAULT_BASE_API_URL.
            user_agent: The User-Agent string to be used in requests.
                        Defaults to DEFAULT_USER_AGENT.
        """
        self.auth_client = auth_client
        self.base_api_url = base_api_url or self.DEFAULT_BASE_API_URL
        self.user_agent = user_agent or self.DEFAULT_USER_AGENT

    def generate_ppsr_report(self, vehicle_id_type: str, vehicle_id: str | dict, **kwargs) -> dict:
        """
        Generates an AU PPSR Report by calling the InfoAgent API.

        Args:
            vehicle_id_type: The type of vehicle identifier.
                             Expected values: "vin", "plateAndState", "chassisAndMake".
            vehicle_id: The vehicle identifier.
                        - For "vin", a string (e.g., "YOUR_VIN_HERE").
                        - For "plateAndState", a dict (e.g., {"plateNumber": "ABC123", "state": "NSW"}).
                        - For "chassisAndMake", a dict (e.g., {"chassisNumber": "123XYZ", "make": "Toyota"}).
            **kwargs: Additional optional parameters to be included in the root of the JSON request body.

        Returns:
            A dictionary containing the report data from the API.

        Raises:
            ReportGenerationError: If report generation fails due to authentication issues,
                                   API errors, network problems, or invalid input.
            ValueError: If `vehicle_id_type` is not one of the expected values or if
                        `vehicle_id` format is incorrect for the given type.
        """
        try:
            access_token = self.auth_client.get_access_token()
        except AuthenticationError as e:
            raise ReportGenerationError(f"Authentication failed: {e}") from e

        report_url = f"{self.base_api_url}/ppsr-reports"

        headers = {
            "Authorization": f"Bearer {access_token}",
            "Content-Type": "application/json",
            "Accept": "application/json",
            "User-Agent": self.user_agent,
        }

        # Construct searchParameters based on vehicle_id_type
        search_params = {}
        valid_id_types = ["vin", "plateAndState", "chassisAndMake"]
        if vehicle_id_type not in valid_id_types:
            raise ValueError(
                f"Invalid vehicle_id_type: '{vehicle_id_type}'. "
                f"Expected one of {valid_id_types}."
            )

        if vehicle_id_type == "vin":
            if not isinstance(vehicle_id, str):
                raise ValueError("vehicle_id must be a string for type 'vin'.")
            search_params["vin"] = vehicle_id
        elif vehicle_id_type == "plateAndState":
            if not isinstance(vehicle_id, dict) or "plateNumber" not in vehicle_id or "state" not in vehicle_id:
                raise ValueError(
                    "vehicle_id must be a dict with 'plateNumber' and 'state' keys for type 'plateAndState'."
                )
            search_params["plateAndState"] = vehicle_id
        elif vehicle_id_type == "chassisAndMake":
            if not isinstance(vehicle_id, dict) or "chassisNumber" not in vehicle_id or "make" not in vehicle_id:
                raise ValueError(
                    "vehicle_id must be a dict with 'chassisNumber' and 'make' keys for type 'chassisAndMake'."
                )
            search_params["chassisAndMake"] = vehicle_id
        
        # Base request body with searchParameters
        request_body = {
            "searchParameters": search_params
        }

        # Add any additional kwargs to the root of the request body
        if kwargs:
            request_body.update(kwargs)

        try:
            response = requests.post(report_url, headers=headers, json=request_body, timeout=30) # 30s timeout
            response.raise_for_status()  # Raises HTTPError for bad responses (4XX or 5XX)
        except requests.exceptions.Timeout:
            raise ReportGenerationError(f"Request to {report_url} timed out.")
        except requests.exceptions.HTTPError as e:
            error_detail = "No additional error detail from server."
            try:
                error_detail = response.json() # Try to get JSON error response
            except requests.exceptions.JSONDecodeError:
                error_detail = response.text # Fallback to raw text
            raise ReportGenerationError(
                f"Failed to generate PPSR report from {report_url}. "
                f"Status: {response.status_code}. Response: {error_detail}. Original error: {e}"
            ) from e
        except requests.exceptions.RequestException as e:
            raise ReportGenerationError(f"An unexpected network error occurred: {e}") from e

        try:
            report_data = response.json()
        except requests.exceptions.JSONDecodeError as e:
            raise ReportGenerationError(
                f"Failed to parse JSON response from {report_url}. Response text: {response.text}"
            ) from e

        return report_data

    def get_ppsr_certificate(self, certificate_id: str, **kwargs) -> bytes:
        """
        Retrieves a PPSR certificate by its ID from the InfoAgent API.

        Args:
            certificate_id: The unique identifier for the PPSR certificate.
            **kwargs: Additional optional parameters to be passed as query parameters.

        Returns:
            The raw content of the PPSR certificate (e.g., PDF binary data).

        Raises:
            CertificateRetrievalError: If certificate retrieval fails due to authentication issues,
                                       API errors (e.g., not found), network problems.
            ValueError: If `certificate_id` is empty or not a string.
        """
        if not certificate_id or not isinstance(certificate_id, str):
            raise ValueError("certificate_id must be a non-empty string.")

        try:
            access_token = self.auth_client.get_access_token()
        except AuthenticationError as e:
            raise CertificateRetrievalError(f"Authentication failed: {e}") from e

        certificate_url = f"{self.base_api_url}/ppsr-certificates/{certificate_id}"

        headers = {
            "Authorization": f"Bearer {access_token}",
            "Accept": "application/pdf", # Assuming PDF content as per common practice
            "User-Agent": self.user_agent,
        }

        try:
            # Pass kwargs as query parameters
            response = requests.get(certificate_url, headers=headers, params=kwargs, timeout=30) # 30s timeout
            response.raise_for_status()  # Raises HTTPError for bad responses (4XX or 5XX)
        except requests.exceptions.Timeout:
            raise CertificateRetrievalError(f"Request to {certificate_url} timed out.")
        except requests.exceptions.HTTPError as e:
            # For certificate retrieval, the error response might not be JSON.
            error_detail = response.text # Default to raw text
            # You could try to parse as JSON if specific error codes suggest it:
            # if response.headers.get('Content-Type') == 'application/json':
            #    try:
            #        error_detail = response.json()
            #    except requests.exceptions.JSONDecodeError:
            #        pass # Keep as text if JSON parsing fails
            raise CertificateRetrievalError(
                f"Failed to retrieve PPSR certificate from {certificate_url}. "
                f"Status: {response.status_code}. Response: {error_detail}. Original error: {e}"
            ) from e
        except requests.exceptions.RequestException as e:
            raise CertificateRetrievalError(f"An unexpected network error occurred: {e}") from e

        # Assuming the response content is the certificate itself (e.g., PDF bytes)
        # If API returns JSON with a link, logic here would need to parse JSON and fetch from link.
        return response.content

if __name__ == '__main__':
    # This is a basic example of how to use the client.
    # In a real application, you would replace mock objects with actual instances
    # and handle exceptions appropriately.

    print("VehicleReportClient module structure defined.")
    print("To test this, you would need a running InfoAgent API and valid credentials.")

    # Mock InfoAgentAuthClient for demonstration purposes
    class MockAuthClient(InfoAgentAuthClient):
        def __init__(self, client_id="mock_id", client_secret="mock_secret", token_url=None):
            super().__init__(client_id, client_secret, token_url)
            self.mock_token_count = 0

        def get_access_token(self) -> str:
            self.mock_token_count += 1
            print(f"[MockAuthClient] get_access_token called (count: {self.mock_token_count}). Returning mock token.")
            if self.client_id == "force_auth_error":
                raise AuthenticationError("Mocked authentication failure.")
            return "mock_access_token_12345"

    print("\n--- Example Usage (Illustrative - No Real API Calls) ---")

    # 1. Successful VIN report (conceptual)
    auth_client_ok = MockAuthClient()
    report_client = VehicleReportClient(auth_client=auth_client_ok, user_agent="TestApp/0.1")
    
    print(f"\nReport client initialized with base URL: {report_client.base_api_url}")
    print(f"Report client User-Agent: {report_client.user_agent}")


    print("\nSimulating generate_ppsr_report call (will not make actual HTTP request):")
    print("This part normally requires a live API endpoint to return a meaningful response.")
    
    try:
        # Example 1: VIN
        print("\nAttempting VIN search (conceptual):")
        # In a real scenario, the requests.post call would be made.
        # We'll just show the setup.
        # report_data_vin = report_client.generate_ppsr_report(
        #     vehicle_id_type="vin",
        #     vehicle_id="TESTVIN1234567890",
        #     customerReference="CustRef123"
        # )
        # print(f"Conceptual VIN report data: {report_data_vin}")
        print("Conceptual call for VIN: TESTVIN1234567890, Ref: CustRef123")
        _ = report_client.generate_ppsr_report(
            vehicle_id_type="vin",
            vehicle_id="TESTVIN1234567890",
            customerReference="CustRef123"
        ) # Assign to _ to avoid unused variable warning if not printing
        print("Conceptual call completed. In a real test, this would mock requests.post.")


        # Example 2: Plate and State
        print("\nAttempting Plate/State search (conceptual):")
        # report_data_plate = report_client.generate_ppsr_report(
        #     vehicle_id_type="plateAndState",
        #     vehicle_id={"plateNumber": "XYZ789", "state": "VIC"},
        #     generateOptions={"option1": True}
        # )
        # print(f"Conceptual Plate/State report data: {report_data_plate}")
        print("Conceptual call for Plate: XYZ789, State: VIC, Options: {'option1': True}")
        _ = report_client.generate_ppsr_report(
            vehicle_id_type="plateAndState",
            vehicle_id={"plateNumber": "XYZ789", "state": "VIC"},
            generateOptions={"option1": True}
        )
        print("Conceptual call completed.")

    except ReportGenerationError as e:
        print(f"Error during conceptual call: {e}")
    except ValueError as e:
        print(f"Input error during conceptual call: {e}")


    # 2. Test with auth error
    auth_client_fail = MockAuthClient(client_id="force_auth_error")
    report_client_auth_fail = VehicleReportClient(auth_client=auth_client_fail)
    print("\nSimulating call that forces an authentication error:")
    try:
        report_client_auth_fail.generate_ppsr_report(vehicle_id_type="vin", vehicle_id="AUTHFAILVIN")
    except ReportGenerationError as e:
        print(f"Caught expected error: {e}")
        if e.__cause__:
            print(f"  Caused by: {e.__cause__}")
    
    # 3. Test invalid vehicle_id_type
    print("\nSimulating call with invalid vehicle_id_type:")
    try:
        report_client.generate_ppsr_report(vehicle_id_type="invalidType", vehicle_id="INVALID")
    except ValueError as e:
        print(f"Caught expected error: {e}")

    # 4. Test invalid vehicle_id format for plateAndState
    print("\nSimulating call with invalid vehicle_id for plateAndState:")
    try:
        report_client.generate_ppsr_report(vehicle_id_type="plateAndState", vehicle_id="NOTAD ICT")
    except ValueError as e:
        print(f"Caught expected error: {e}")

    # 5. Conceptual call for get_ppsr_certificate
    print("\nSimulating get_ppsr_certificate call (will not make actual HTTP request):")
    try:
        # In a real scenario, this would make a GET request for a PDF.
        print("Conceptual call for certificate ID: CERT12345XYZ, download: true")
        # certificate_content = report_client.get_ppsr_certificate("CERT12345XYZ", download="true")
        # print(f"Conceptual certificate content length: {len(certificate_content)} bytes (if successful)")
        # For now, just simulate the call setup
        _ = report_client.get_ppsr_certificate("CERT12345XYZ", download="true")
        print("Conceptual certificate call completed. In a real test, this would mock requests.get.")
    except CertificateRetrievalError as e:
        print(f"Error during conceptual certificate call: {e}")
    except ValueError as e:
        print(f"Input error during conceptual certificate call: {e}")

    # 6. Test get_ppsr_certificate with auth error
    report_client_auth_fail_cert = VehicleReportClient(auth_client=auth_client_fail) # Uses the auth_client_fail from above
    print("\nSimulating get_ppsr_certificate call that forces an authentication error:")
    try:
        report_client_auth_fail_cert.get_ppsr_certificate("CERT_AUTH_FAIL")
    except CertificateRetrievalError as e:
        print(f"Caught expected error for certificate retrieval: {e}")
        if e.__cause__:
            print(f"  Caused by: {e.__cause__}")
            
    # 7. Test get_ppsr_certificate with empty certificate_id
    print("\nSimulating get_ppsr_certificate call with empty certificate_id:")
    try:
        report_client.get_ppsr_certificate("")
    except ValueError as e:
        print(f"Caught expected error for empty certificate_id: {e}")

    print("\n--- End of Example Usage ---")
    print("Module vehicle_reporter.py updated with get_ppsr_certificate method.")

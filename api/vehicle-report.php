<?php

// Adjust path based on actual location of client files relative to this 'api' directory
require_once __DIR__ . '/../InfoAgentAuthClient.php';
require_once __DIR__ . '/../VehicleReportClient.php';

header('Content-Type: application/json');

// IMPORTANT: Hardcoded credentials for demonstration.
// In a production environment, store these securely (e.g., environment variables, secure config file).
$clientId = "UIjBTaQWqDrOUwrIKF6k";
$clientSecret = "b1b562dd-0fdf-45cd-9af1-9189cc2424a3";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Only POST method is accepted.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload: ' . json_last_error_msg()]);
    exit;
}

if (!isset($data->searchType) || !isset($data->searchParameters)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Missing required fields: searchType or searchParameters.']);
    exit;
}

// Basic validation for searchParameters based on type, can be expanded
if (empty((array)$data->searchParameters)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'searchParameters cannot be empty.']);
    exit;
}


try {
    $authClient = new InfoAgentAuthClient($clientId, $clientSecret);
    $vehicleReportClient = new VehicleReportClient($authClient);

    // Ensure searchParameters is passed as an array to the client method
    $reportData = $vehicleReportClient->generatePpsrReport($data->searchType, (array)$data->searchParameters);

    // Placeholder: Assume certificateId might be in the reportData. Adjust based on actual API response.
    $certificateId = null;
    if (isset($reportData['reportDetails']['certificateId'])) { // Example path
        $certificateId = $reportData['reportDetails']['certificateId'];
    } elseif (isset($reportData['certificateId'])) { // Simpler path
        $certificateId = $reportData['certificateId'];
    }
    // Even if not found, it's not an error for the report itself.

    http_response_code(200); // OK
    echo json_encode([
        'success' => true,
        'reportData' => $reportData,
        'certificateId' => $certificateId
    ]);

} catch (AuthenticationException $e) {
    http_response_code(500); // Internal Server Error (could be 401 if specifically for client auth issues to API)
    // Log the detailed error for server admin, return a more generic message to client
    error_log("AuthenticationException in vehicle-report.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Authentication failed while contacting external service.']);
} catch (ReportApiException $e) {
    // ReportApiException might indicate issues like API downtime, or invalid data sent to *that* API
    // We might return 500, or 400 if the error message suggests input error to the downstream API
    // For simplicity, using 500 for now, but inspect $e->getMessage() for more specific codes
    http_response_code(500);
    error_log("ReportApiException in vehicle-report.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to generate vehicle report: ' . $e->getMessage()]);
} catch (InvalidArgumentException $e) {
    http_response_code(400); // Bad Request (likely due to invalid searchType from client)
    error_log("InvalidArgumentException in vehicle-report.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Invalid input: ' . $e->getMessage()]);
} catch (\Exception $e) {
    http_response_code(500); // Internal Server Error
    error_log("Generic Exception in vehicle-report.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
}

?>

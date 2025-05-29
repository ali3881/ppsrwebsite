<?php

// Adjust path based on actual location of client files relative to this 'api' directory
require_once __DIR__ . '/../lib/InfoAgentAuthClient.php';
require_once __DIR__ . '/../lib/VehicleReportClient.php';

// IMPORTANT: Hardcoded credentials for demonstration.
// In a production environment, store these securely (e.g., environment variables, secure config file).
$clientId = "UIjBTaQWqDrOUwrIKF6k";
$clientSecret = "b1b562dd-0fdf-45cd-9af1-9189cc2424a3";

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Content-Type: application/json');
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Only GET method is accepted.']);
    exit;
}

$certificateId = $_GET['id'] ?? null;

if (empty($certificateId)) {
    header('Content-Type: application/json');
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Certificate ID is required.']);
    exit;
}

try {
    $authClient = new InfoAgentAuthClient($clientId, $clientSecret);
    $vehicleReportClient = new VehicleReportClient($authClient);

    $certificateData = $vehicleReportClient->getPpsrCertificate($certificateId);

    // If successful, output PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="PPSR_Certificate_' . basename($certificateId) . '.pdf"');
    header('Content-Length: ' . strlen($certificateData)); // Good practice to set content length
    echo $certificateData;
    exit;

} catch (AuthenticationException $e) {
    // Ensure JSON headers are set before any output for errors
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    http_response_code(500); // Or 401 if more specific
    error_log("AuthenticationException in vehicle-certificate.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Authentication failed while contacting external service.']);
} catch (ReportApiException $e) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    // Check status code from exception if available, otherwise default
    // For simplicity, using 500 for now, but could be 404 if cert not found, etc.
    http_response_code(500); 
    error_log("ReportApiException in vehicle-certificate.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve certificate: ' . $e->getMessage()]);
} catch (InvalidArgumentException $e) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    http_response_code(400); // Bad Request (likely due to empty certificateId if validation was there)
    error_log("InvalidArgumentException in vehicle-certificate.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Invalid input: ' . $e->getMessage()]);
} catch (\Exception $e) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    http_response_code(500); // Internal Server Error
    error_log("Generic Exception in vehicle-certificate.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
}

?>

document.addEventListener('DOMContentLoaded', () => {
    const searchTypeSelect = document.getElementById('searchType');
    const vinGroup = document.getElementById('vinGroup');
    const plateStateGroup = document.getElementById('plateStateGroup');
    const chassisMakeGroup = document.getElementById('chassisMakeGroup');

    const vinInput = document.getElementById('vinInput');
    const plateNumberInput = document.getElementById('plateNumberInput');
    const stateInput = document.getElementById('stateInput');
    const chassisNumberInput = document.getElementById('chassisNumberInput');
    const makeInput = document.getElementById('makeInput');

    const vehicleReportForm = document.getElementById('vehicleReportForm');
    const reportResultArea = document.getElementById('reportResultArea');
    const reportTextContent = document.getElementById('reportTextContent'); // For the main report text
    const certificateActionsArea = document.getElementById('certificateActionsArea'); // For the certificate button
    const loadingIndicator = document.getElementById('loadingIndicator');
    const submitButton = document.getElementById('submitReportRequest');

    function displayCertificateButton(reportId, certificateId) {
        certificateActionsArea.innerHTML = ''; // Clear previous content
        
        const button = document.createElement('button');
        button.id = 'downloadCertificateButton';
        button.textContent = 'Download PPSR Certificate';
        
        button.addEventListener('click', async () => {
            console.log(`Download certificate clicked. Report ID: ${reportId}, Certificate ID: ${certificateId}`);
            button.disabled = true;
            button.textContent = 'Downloading...';

            try {
                const response = await fetch(`/api/vehicle-certificate.php?id=${certificateId}`, {
                    method: 'GET',
                });

                if (response.ok) {
                    const blob = await response.blob();
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `PPSR_Certificate_${certificateId}.pdf`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                    reportTextContent.textContent += '\nCertificate downloaded successfully.';
                } else {
                    const errorData = await response.json().catch(() => ({ message: `Failed to download certificate. Status: ${response.status}` }));
                    alert(`Error downloading certificate: ${errorData.message || 'Unknown error'}`);
                    reportTextContent.textContent += `\nFailed to download certificate: ${errorData.message || response.statusText}`;
                }
            } catch (error) {
                console.error('Error fetching certificate:', error);
                alert(`Error fetching certificate: ${error.message}`);
                reportTextContent.textContent += `\nError fetching certificate: ${error.message}`;
            } finally {
                button.disabled = false;
                button.textContent = 'Download PPSR Certificate';
            }
        });
        
        certificateActionsArea.appendChild(button);
        certificateActionsArea.classList.remove('hidden');
    }

    function handleSearchTypeChange() {
        const selectedType = searchTypeSelect.value;

        // Hide all groups first
        vinGroup.classList.add('hidden');
        plateStateGroup.classList.add('hidden');
        chassisMakeGroup.classList.add('hidden');

        // Show the relevant group
        if (selectedType === 'vin') {
            vinGroup.classList.remove('hidden');
        } else if (selectedType === 'plateAndState') {
            plateStateGroup.classList.remove('hidden');
        } else if (selectedType === 'chassisAndMake') {
            chassisMakeGroup.classList.remove('hidden');
        }
    }

    vehicleReportForm.addEventListener('submit', (event) => {
        event.preventDefault(); // Prevent actual form submission

        reportTextContent.innerHTML = ''; // Clear previous report text
        certificateActionsArea.innerHTML = ''; // Clear previous certificate button
        certificateActionsArea.classList.add('hidden'); // Hide certificate area
        reportResultArea.classList.remove('success', 'error'); // Clear previous styling
        loadingIndicator.classList.remove('hidden');
        submitButton.disabled = true;

        const searchType = searchTypeSelect.value;
        let requestPayload = {
            searchType: searchType,
            searchParameters: {}
        };
        let isValid = true;
        let errorMessage = '';

        if (searchType === 'vin') {
            const vin = vinInput.value.trim();
            if (!vin) {
                isValid = false;
                errorMessage = 'VIN is required.';
            } else {
                requestPayload.searchParameters.vin = vin;
            }
        } else if (searchType === 'plateAndState') {
            const plateNumber = plateNumberInput.value.trim();
            const state = stateInput.value;
            if (!plateNumber || !state) {
                isValid = false;
                errorMessage = 'Plate Number and State are required.';
            } else {
                requestPayload.searchParameters.plateNumber = plateNumber;
                requestPayload.searchParameters.state = state;
            }
        } else if (searchType === 'chassisAndMake') {
            const chassisNumber = chassisNumberInput.value.trim();
            const make = makeInput.value.trim();
            if (!chassisNumber || !make) {
                isValid = false;
                errorMessage = 'Chassis Number and Make are required.';
            } else {
                requestPayload.searchParameters.chassisNumber = chassisNumber;
                requestPayload.searchParameters.make = make;
            }
        }

        if (!isValid) {
            reportTextContent.textContent = errorMessage;
            reportResultArea.classList.add('error');
            loadingIndicator.classList.add('hidden');
            submitButton.disabled = false;
            return;
        }

        console.log("Request Payload:", JSON.stringify(requestPayload, null, 2));

        // Actual API call
        fetch('/api/vehicle-report.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(requestPayload),
        })
        .then(response => {
            if (!response.ok) {
                // Try to parse error response, otherwise use statusText
                return response.json().then(errData => {
                    throw new Error(errData.message || `HTTP error! Status: ${response.status}`);
                }).catch(() => {
                    throw new Error(`HTTP error! Status: ${response.status} - ${response.statusText}`);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                reportTextContent.textContent = `Report Generated Successfully:\n${JSON.stringify(data.reportData, null, 2)}`;
                reportResultArea.classList.add('success');
                reportResultArea.classList.remove('error');
                if (data.certificateId) {
                    // Assuming reportData might contain a reportId, or pass a placeholder.
                    const reportId = data.reportData && data.reportData.reportId ? data.reportData.reportId : 'N/A';
                    displayCertificateButton(reportId, data.certificateId);
                }
            } else {
                reportTextContent.textContent = `Error generating report: ${data.message || 'Unknown error'}`;
                reportResultArea.classList.add('error');
                reportResultArea.classList.remove('success');
                certificateActionsArea.classList.add('hidden');
            }
        })
        .catch(error => {
            console.error('Error fetching report:', error);
            reportTextContent.textContent = `Failed to fetch report: ${error.message}`;
            reportResultArea.classList.add('error');
            reportResultArea.classList.remove('success');
            certificateActionsArea.classList.add('hidden');
        })
        .finally(() => {
            loadingIndicator.classList.add('hidden');
            submitButton.disabled = false;
        });
    });

    // Attach event listener for search type change
    searchTypeSelect.addEventListener('change', handleSearchTypeChange);

    // Call handler once on load to set initial visibility
    handleSearchTypeChange();

    // Add basic styling for success/error messages to reportResultArea
    const style = document.createElement('style');
    style.textContent = `
        .report-result.success {
            border-color: green;
            color: green;
            background-color: #e6ffe6;
        }
        .report-result.error {
            border-color: red;
            color: red;
            background-color: #ffe6e6;
        }
        #reportResultArea.success {
            border-color: green;
            color: green;
            background-color: #f0fff0;
        }
        #reportResultArea.error {
            border-color: red;
            color: red;
            background-color: #fff0f0;
        }
    `;
    document.head.appendChild(style);
});

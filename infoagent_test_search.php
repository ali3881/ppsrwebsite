<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InfoAgent Vehicle Report Test</title>

    <!-- TODO: Replace 'path/to/your/main_site_stylesheet.css' with the actual path to your website's main CSS file for consistent styling. -->
    <!-- You may need to inspect your existing website's HTML source to find the correct path to your main stylesheet. -->
    <!-- <link rel="stylesheet" href="path/to/your/main_site_stylesheet.css"> -->

    <!-- Link to our form-specific CSS -->
    <link rel="stylesheet" href="/css/style.css">

    <!-- Link to our JavaScript -->
    <script src="/js/script.js" defer></script>
</head>
<body>
    <h1>InfoAgent API Test Page</h1>

    <div class="container">
        <h2>Vehicle Safety Report Lookup</h2>
        <form id="vehicleReportForm">
            <div class="form-group">
                <label for="searchType">Search Type:</label>
                <select id="searchType" name="searchType">
                    <option value="vin" selected>VIN</option>
                    <option value="plateAndState">Plate and State</option>
                    <option value="chassisAndMake">Chassis and Make</option>
                </select>
            </div>

            <div id="vinGroup" class="form-group">
                <label for="vinInput">VIN:</label>
                <input type="text" id="vinInput" name="vin">
            </div>

            <div id="plateStateGroup" class="form-group input-group hidden">
                <div>
                    <label for="plateNumberInput">Plate Number:</label>
                    <input type="text" id="plateNumberInput" name="plateNumber">
                </div>
                <div>
                    <label for="stateInput">State:</label>
                    <select id="stateInput" name="state">
                        <option value="">Select State</option>
                        <option value="ACT">ACT</option>
                        <option value="NSW">NSW</option>
                        <option value="NT">NT</option>
                        <option value="QLD">QLD</option>
                        <option value="SA">SA</option>
                        <option value="TAS">TAS</option>
                        <option value="VIC">VIC</option>
                        <option value="WA">WA</option>
                    </select>
                </div>
            </div>

            <div id="chassisMakeGroup" class="form-group input-group hidden">
                <div>
                    <label for="chassisNumberInput">Chassis Number:</label>
                    <input type="text" id="chassisNumberInput" name="chassisNumber">
                </div>
                <div>
                    <label for="makeInput">Make:</label>
                    <input type="text" id="makeInput" name="make">
                </div>
            </div>

            <button type="submit" id="submitReportRequest">Get Report</button>
        </form>

        <div id="loadingIndicator" class="hidden">Loading...</div>
        <div id="reportResultArea">
            <div id="reportTextContent"></div> <!-- For existing text like "Report data would appear here" -->
            <div id="certificateActionsArea" class="hidden"></div> <!-- New area for certificate button -->
        </div>
    </div>
    <!-- Note: The original script.js link from vehicle_report_form.html is not needed here -->
    <!-- as it's already included in the <head> section with the 'defer' attribute and correct path. -->
</body>
</html>

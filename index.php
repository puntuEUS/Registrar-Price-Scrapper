<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<head>
    <title>Web scrapper</title>
</head>
<style>
    table {
        border-collapse: collapse;
    }

    table, th, td {
        border: 2px solid #ddd;
        padding: 3px;
    }
</style>
<body>
<?php
include 'scrapper.php';
include 'dinahosting.php';

/* <------------------- Start of configuration -----------------> */
date_default_timezone_set("Europe/Madrid"); // Set this according to the timezone of the server
ini_set('max_execution_time', 250); // Not recommended to bellow 200
mb_internal_encoding("UTF-8"); // Character encoding

/**
 * Information to access the database. The database will be used to update registrar prices in the database table, and keep an historic track of prices
 *
 *  NOTE: Complete database server information accordingly
 */
$databaseConnection = new Database(); //New database object
$databaseConnection->dbHost = "";
$databaseConnection->dbUsername = "";
$databaseConnection->dbPassword = "";
$databaseConnection->dbName = "";
$databaseConnection->dbPort = 0;


/**
 * New php mailer object. An e-mail will be sent with the summary of the results of the price scrapping
 *
 *  NOTE: Complete mail server information accordingly
 */
$configPhpMailer = new Mailer();
$configPhpMailer->SMTPDebug = 0;
$configPhpMailer->host = "";
$configPhpMailer->port = 0;
$configPhpMailer->encryption = "tls";
$configPhpMailer->SMTPAuth = true;
$configPhpMailer->username = "";
$configPhpMailer->password = "";
$configPhpMailer->address = "";
$configPhpMailer->emailSubject = "";
$configPhpMailer->to = "";
$configPhpMailer->subject = "";
$configPhpMailer->attachedFiles = "";
$configPhpMailer->message = ""; // This will be filled after scrapping the prices.


/* <------------------- End of configuration -----------------> */

//Json file with the websites and XPaths to scrap
$priceList = scrapper(readJson("domains.json", "registrar"));






/* <------------------- Start of Amen -----------------> */


/**
 * PDF reading is only needed for Amen.fr. If you don't require such registrar and/or do not need to read pdf files, you can remove this piece of code, and folder "pdf2htmlEX" could be removed too
 *
 * Requirements: pdf2htmlEX library is needed in order to read pdfs, but such library is not available in all OS versions. You can find installation steps in the following link: https://github.com/coolwanglu/pdf2htmlEX/wiki/Building
 */


// Downloads pdf with the price list
file_put_contents("amen.pdf", fopen("https://www.amen.fr/wp-content/uploads/Tarifs-Publics_Noms-de-domaine_AMEN_2018.pdf", 'r'));
// Converts the pdf to html
if (PHP_OS == "Linux") {
    shell_exec("pdf2htmlEX --dest-dir " . __DIR__ . " " . __DIR__ . "/amen.pdf");
} else {
    shell_exec(__DIR__ . "\pdf2htmlEX\pdf2htmlEX.exe --dest-dir " . __DIR__ . " " . __DIR__ . "/amen.pdf");
}

$XPath = array('//*[@id="pfe"]/div[1]/div[36]/div[2]/span[3]/text()', '//*[@id="pfe"]/div[1]/div[37]/div[2]/span[3]/text()');
exec(generateCommandCasperJS("default.js", "amen.html", $XPath), $output);

unlink("amen.pdf");
unlink("amen.html");

$amen[] = "amen-fr";
$formattedPrice = formatPrice($output);

foreach ($formattedPrice as $element) {
    array_push($amen, $element);
}

$priceList[] = $amen;

/* <------------------- End of Amen -----------------> */


/* <------------------- Beginning of Dinahosting -----------------> */

/**
 * Unlike other registrars that are scrapped in order to extract their creation/renew prices, Dinahosting's prices are extracted through API, so an API key is needed which will be provided by Dinahosting with no cost
 */

$dinahostingData[] = "dinahosting";
$response = dinahostingPrice("create", "eus");

if ($response['responseCode'] == 1000) {
    $dinahostingData[] = $response['data'];
} else {
    $dinahostingData[] = null;
}

$response = dinahostingPrice("renew", "eus");
if ($response['responseCode'] == 1000) {
    $dinahostingData[] = $response['data'];
} else {
    $dinahostingData[] = null;
}

$dinahostingData[] = "Euro";
$priceList[] = $dinahostingData;

/* <------------------- End of Dinahosting -----------------> */


/* <------------------- Start of Updating the database -----------------> */

$query = ("IF NOT EXISTS CREATE TABLE `RegistrarPricesHistoric` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `registrar` varchar(120) NOT NULL DEFAULT '',
  `creationPrice` decimal(6,2) DEFAULT NULL,
  `renewPrice` decimal(6,2) DEFAULT NULL,
  `date` date NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

runSqlQuery($databaseConnection, $query);

foreach ($priceList as $registrar) {
    unset($query);

    // Only updates the database if they are not empty or null
    if ($registrar[1] != null || $registrar[1] != "") { // If creation price ($registrar[1]) is not null/empty, update registrar ($registrar[0])
        $query = ""; // Insert your database query
        runSqlQuery($databaseConnection, $query);
    }

    if ($registrar[2] != null || $registrar[2] != "") { // If renew price ($registrar[2]) is not null/empty, update registrar ($registrar[0])
        $query = ""; // Insert your database query
        runSqlQuery($databaseConnection, $query);
    }

    if ($registrar[3] != null || $registrar[3] != "") { // If currency ($registrar[3]) is not null/empty, update registrar ($registrar[0])
        $query = ""; // Insert your database query
        runSqlQuery($databaseConnection, $query);
    }

    $query = "INSERT INTO RegistrarPricesHistoric (registrar, creationPrice, renewPrice, date) VALUES ( '" . $registrar[0] . "', " . $registrar[1] . ", " . $registrar[2] . ", now())";
    runSqlQuery($databaseConnection, $query);
}

$message = "Date of the price scrapping: " . date('Y/m/d', time()) . "<br /><br />";
usort($priceList, 'compare'); // Prices are sorted alphabetically

echo $message .= generatePriceTable($databaseConnection, $priceList);


/* <------------------- End of Updating the database -------------------> */



/* <------------------- Start of Send email -----------------> */
$configPhpMailer->message = $message; // Adds the body of the message to the $configPhpMailer object

try {
    sendMessage($configPhpMailer);
} catch (phpmailerException $e) {
    echo "Error: " . $e->getMessage();
}

/* <------------------- End of Send email -----------------> */

?>
</body>
</html>

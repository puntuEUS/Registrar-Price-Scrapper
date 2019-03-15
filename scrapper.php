<?php
require 'lib/PHPMailer/PHPMailerAutoload.php';

/**
 * @property string dbHost
 * @property string dbUsername
 * @property string dbPassword
 * @property string dbName
 * @property int dbPort
 */
class Database
{
    public $host;
    public $username;
    public $password;
    public $name;
    public $port;
}

class Mailer
{
    public $SMTPDebug;
    public $host;
    public $port;
    public $encryption;
    public $SMTPAuth;
    public $username;
    public $password;
    public $address;
    public $emailSubject;
    public $to;
    public $subject;
    public $attachedFiles; // Leave empty if there are no files to send
    public $message; // This will be filled after getting the prices
}

/* <------------- Start of the functions -------------> */
/**
 * Takes an array of numbers and changes the ',' to '.', removes any characters that aren't
 * numbers, ',' and '.'. Then it detects the currency and returns all in an array.
 *
 * @param array $price <p>
 * Array of the prices to format
 * </p>
 * @param string $currency [optional]
 * Manually introduce currency otherwise it will automatically be detected
 * @return array <p>
 * Returns the prices formatted and with their currency
 * </p>
 */
function formatPrice($price, $currency = null)
{
    $data = preg_replace('/\s+/', '', $price); // Removes blanks
    $data = str_replace(',', '.', $data); // Decimals with '.' instead of ','

    if ($currency == null) {

        $i = 0;
        do {
            $currency = detectCurrency($data[$i]);
            $i++;
        } while ($i < sizeof($data) - 1 && $currency == "Other" && array_key_exists($i, $data));
        unset($i);
    }

    $data = preg_replace('/[^0-9.]+/', '', $data);

    $data[] = $currency;
    return $data;
}

/**
 * Searches for currency signs and returns the detected currency.
 *
 * @param $price string
 * Price with currency character
 * @return string
 * Returns the detected price or "Other" if none is detected
 */
function detectCurrency($price)
{
    if (strpos($price, '$') !== false || strpos($price, 'USD') !== false) {
        return "Dollar";

    } elseif (strpos($price, '£') !== false || strpos($price, 'GBP') !== false) {
        return "Pound";

    } elseif (strpos($price, '€') !== false || strpos($price, 'EUR') !== false) {
        return "Euro";

    } else {
        return "Other";
    }
}

/**
 * Reads and decodes the json file
 *
 * @param $jsonFile
 * The json file to be decoded
 * @param string $identifier
 * @return mixed
 * Returns the json decoded to be processed
 */
function readJson($jsonFile = "domains.json", $identifier = "registrar")
{
    $json = file_get_contents($jsonFile);
    $decodedFile = json_decode($json, true);

    return $decodedFile[$identifier];
}

/**
 * Generates a random integer within the set range
 *
 * @param int $min [optional]
 * Min number
 * @param int $max [optional]
 * Max numebr
 * @param int $length [optional]
 * Length of the number
 * @return bool|string
 * Returns the generated number
 */
function rndChar($min = 0, $max = 26, $length = 10)
{
    return substr(md5(microtime()), rand($min, $max), $length);
}

/**
 * Removes VAT value from the passed integer
 *
 * @param int $price
 * <p>Value to remove the VAT from</p>
 * @param int $vat
 * <p>Rate of vat in percentage ej(for 20% -> $vat=20)</p>
 * @param int $n_decimals [optional]
 * <p>The number of decimals after the '.'. By default it's 2.</p>
 * @return float
 * <p>Returns the value without the VAT</p>
 */
function removeVat($price, $vat, $n_decimals = 2)
{
    return (float)(number_format($price / (1 + ($vat / 100)), $n_decimals, '.', ''));
}

/**
 * Prints an array in a readable format
 *
 * @param $array
 */
function prettyPrint($array)
{
    print("<pre>" . print_r($array, true) . "</pre>");
}

/**
 * Compares the values of the index of two arrays
 *
 * @param $arrayA
 * @param $arrayB
 * @param int $index
 * @return bool
 */
function compare($arrayA, $arrayB, $index = 0)
{
    return ($arrayA[$index] > $arrayB[$index]);
}

/**
 * Generates a table with the prices from the array
 *
 * @param $databaseConnection
 * @param $priceArray
 * @return string
 */
function generatePriceTable($databaseConnection, $priceArray)
{
    //style="border: 2px solid #ddd; padding: 3px; "
    $table = "<table style=\"border: 2px solid #ddd; padding: 3px; border-collapse: collapse; \">
                <tr style=\"border: 2px solid #ddd; padding: 3px; \">
                    <th style=\"border: 2px solid #ddd; padding: 3px; \">Registrar</th>
                    <th style=\"border: 2px solid #ddd; padding: 3px; \">Creation</th>
                    <th style=\"border: 2px solid #ddd; padding: 3px; \" colspan=" . (sizeof($priceArray[0]) - 2) . ">Renewal  || Currency </th>
                    <th style=\"border: 2px solid #ddd; padding: 3px; \">Old Creation</th><th>Old Renewal</th>
                </tr>";

    foreach ($priceArray as $row) {
        $table .= "<tr>";

        $query = ("SELECT creationPrice, renewPrice
                  FROM RegistrarPricesHistoric
                  WHERE registrar = '" . $row[0] . "' AND date = (SELECT MAX(date) FROM RegistrarPricesHistoric
WHERE registrar = '" . $row[0] . "');");


        $prices = runSqlQuery($databaseConnection, $query)->fetch_assoc();

        $registerPrice = $prices["creationPrice"];
        $renewPrice = $prices["renewPrice"];

        foreach ($row as $cell) {
            if ($cell == null || $cell == "") {
                $table .= "<td style=\"border: 2px solid #ddd; padding: 3px; \"><span style=\"color: red; \">null</span></td>";
            } else if ($row[1] != $registerPrice || $row[2] != $renewPrice) {
                $table .= "<td style=\"border: 2px solid #ddd; padding: 3px; \"><span style=\"color: red; \">" . $cell . "</span></td>";

            } else {
                $table .= "<td style=\"border: 2px solid #ddd; padding: 3px; \">" . $cell . "</td>";
            }

        }

        //Adds the old prices at the end of each row
        if ($registerPrice == null) {
            $table .= "<td style=\"border: 2px solid #ddd; padding: 3px; \"><span style=\"color: red; text-align: center; \">null</span></td>"; // If there no register text is "null" in red

        } else if ($row[1] != $registerPrice) {
            $table .= "<td style=\"border: 2px solid #ddd; padding: 3px; \"><span style=\"color: red; \">" . $registerPrice . "</span></td>"; // If the prices scrapped and in the database are different  they are displayed in red

        } else {
            $table .= "<td style=\"border: 2px solid #ddd; padding: 3px; text-align: center; \">--------</td>";
        }

        if ($renewPrice == null) {
            $table .= "<td style=\"border: 2px solid #ddd; padding: 3px; \"><span style=\"color: red; text-align: center; \">null</span></td>";
        } else if ($row[2] != $renewPrice) {
            $table .= "<td style=\"border: 2px solid #ddd; padding: 3px; \"><span style=\"color: red; \">" . $renewPrice . "</span></td>";

        } else {
            $table .= "<td style=\"border: 2px solid #ddd; padding: 3px; text-align: center; \">--------</td>";

        }

        $table .= "</tr>";
    }

    $table .= "</table>";
    $table .= "<small>*null prices won't be inserted into the database</small>";

    return $table;
}


/**
 * Generates a command to execute casperJS passing the js file, the url and the XPath
 *
 * @param string $js_script
 * @param string $url
 * @param array $XPath
 * @param bool $url_query
 * @param bool $domain
 * @return null|string
 */
function generateCommandCasperJS($js_script, $url, $XPath, $url_query = false, $domain = false)
{
    $command = null;

    /*
     * If url_query is true it creates a search for a domain by creating random characters followed by the domain
     *
     * Example:
     * Giving
     * url=https://www.regitrar_website.eus
     * rndChar() = kduakfi
     * $domain = eus
     *
     * Will pass to casperjs
     * https://www.regitrar_website.eus/search=bdsaibdas.eus
     */
    $command = 'casperjs ';
    $command .= escapeshellarg(__DIR__ . '/js/' . $js_script) . ' '; // Path to the script

    if ($url_query) {
        $command .= escapeshellarg($url . rndChar() . $domain) . ' '; //URL of the website
    } else {
        if ($XPath != null) {
            $command .= escapeshellarg($url) . ' '; //URL of the website
        }
    }

    if ($XPath != null) {
        foreach ($XPath as $path) {
            $command .= escapeshellarg($path) . ' '; //XPaths
        }
    }

    return $command;
}

/**
 * Sends an email
 *
 * @param $configPhpMailer
 * @return string
 * Whether the email was successfully sent or not.
 * @throws phpmailerException
 */
function sendMessage($configPhpMailer)
{
    //Create a new PHPMailer instance
    $mail = new PHPMailer;

    //Tell PHPMailer to use SMTP
    $mail->isSMTP();

    //Enable SMTP debugging
    // 0 = off (for production use)
    // 1 = client messages
    // 2 = client and server messages
    $mail->SMTPDebug = $configPhpMailer->SMTPDebug;

    //Ask for HTML-friendly debug output
    // $mail->Debugoutput = 'html';

    //Set the hostname of the mail server
    $mail->Host = $configPhpMailer->host;

    //Set the SMTP port number - 587 for authenticated TLS, a.k.a. RFC4409 SMTP submission
    $mail->Port = $configPhpMailer->port;

    //Set the encryption system to use - ssl (deprecated) or tls
    $mail->SMTPSecure = $configPhpMailer->encryption;

    //Whether to use SMTP authentication
    $mail->SMTPAuth = $configPhpMailer->SMTPAuth;

    //Username to use for SMTP authentication - use full email address for gmail
    $mail->Username = $configPhpMailer->username;

    //Password to use for SMTP authentication
    $mail->Password = $configPhpMailer->password;

    //Set who the message is to be sent from
    $mail->setFrom($configPhpMailer->address, $configPhpMailer->emailSubject);

    //Set an alternative reply-to address
    // $mail->addReplyTo('replyto@example.com', 'First Last');

    //Set who the message is to be sent to
    // $mail->addAddress( 'info@domeinuak.eus', 'Info Domeinuak' );

    $mail->addAddress($configPhpMailer->to);

    if ("" !== $configPhpMailer->attachedFiles && count($configPhpMailer->attachedFiles) <= 2) { // Normalean ez da fitxategi erantsirik bidaliko. Bektore bidez definitzen direnez, bidalketa ez deskontrolatzeko bi
        // fitxategi soilik erantsi daitezke, oraingoz hori izango delako izango duen erabilera: ongi-etorri eta ezabatze mezua bidaltzea
        for ($i = 0; $i < count($configPhpMailer->attachedFiles); $i++) {

            $mail->AddAttachment($configPhpMailer->attachedFiles[$i]);
        }
    }

    //Set the subject line
    $mail->Subject = $configPhpMailer->subject;

    //Read an HTML message body from an external file, convert referenced images to embedded,
    //convert HTML into a basic plain-text alternative body
    // $mail->msgHTML(file_get_contents('contents.html'), dirname(__FILE__));
    $mail->msgHTML($configPhpMailer->message);

    //Replace the plain text body with one created manually
    // $mail->AltBody = 'This is a plain-text message body';


    //send the message, check for errors
    if (!$mail->send()) {
        return "Mailer Error: " . $mail->ErrorInfo;
    } else {
        return "Message sent!";
    }
}

/**
 * Goes through the json file and returns prices in an array
 *
 * @param $json
 * <p>JSON file with all the registrar, url, xpath and options for the program</p>
 * @return array
 * Returns an array with the prices extracted
 */
function scrapper($json)
{
    $command = null;
    $priceList = null;

    // Loops through each registrar in domains.json
    foreach ($json as $registrar) {
        $error = false;
        $url = null;
        $XPath = null;

        $prices[] = $registrar["name"];

        $url_query = isset($registrar['url_query']) ? $registrar['url_query'] : false; //Checks whether url_query is set
        $js_script = isset($registrar['js_script']) ? $registrar['js_script'] : "default.js"; // If a custom js is set in the json, execute it, otherwise it will use default.js
        $query_domain = isset($registrar['domain']) ? $registrar['domain'] : ".eus"; //If there are not domains set it will use .eus as default

        try {

            // Validates if there is a url set
            if (isset($registrar['url'])) {
                $url = $registrar['url'];
            } else {
                throw new Exception($registrar["name"] . " is missing the URL!<br /><br />");
            }

            //Validates if there is a XPath set
            if (isset($registrar['XPath'])) {
                $XPath = $registrar['XPath'];
            } else {
                throw new Exception($registrar["name"] . " is missing the XPath!");
            }

        } catch (exception $e) {
            $error = true;
            echo "Error: " . $e->getMessage();
        }
        // If there are no errors it runs the function
        $error ?: $command = generateCommandCasperJS($js_script, $url, $XPath, $url_query, $query_domain);


        // In case of timeout it will retry 3 times
        $retryCount = 0;
        do {
            // It runs the generated command in casperJS to get the prices
            $casperReturn = array();
            exec($command, $casperReturn);

            // If the response contains the word "timeout" there has problem occurred
            if (strpos($casperReturn[0], 'timeout') !== false) {
                $casperTimeout = true;
//              echo "Timeout, retrying... <br /><br />";
//              echo "Attempt " . ($retryCount + 1) . " of 3<br /><br />";
                $retryCount++;
            } else {
                $casperTimeout = false;
            }
        } while ($casperTimeout || $retryCount == 3);


        // If there is a currency set it will pass it when formatting the price, otherwise it will be automatically detected
        if (isset($registrar["currency"])) {
            if ($registrar["currency"] == null) {
                $formattedPrice = formatPrice($casperReturn); // No currency set and it will be detected.
            } else {
                $formattedPrice = formatPrice($casperReturn, $registrar["currency"]); // Currency set.
            }
        } else {
            $formattedPrice = formatPrice($casperReturn); // No currency set and it will be detected.
        }

        //Adds the prices to the array of the current registrar
        foreach ($formattedPrice as $element) {
            array_push($prices, $element);
        }

        // If there is a vat value it wil recalculate the price with the vat removed
        if (isset($registrar["vat"])) {
            if ($registrar["vat"] != 0) {
                for ($i = 1; $i < sizeof($prices) - 1; $i++) {
                    $prices[$i] = removeVat($prices[$i], $registrar["vat"]);
                }
            }
        }

        //Adds the prices of the current registrar to the list of all of the prices
        $priceList[] = $prices;

        unset($casperReturn);
        unset($prices);
    }

    return $priceList;
}

/**
 * Takes a query and executes it in the database.
 *
 * @param $database
 * @param string $query
 * @return array|bool|mysqli_result|null
 */
function runSqlQuery($database, $query)
{
    $connection = new mysqli($database->dbHost, $database->dbUsername, $database->dbPassword, $database->dbName, $database->dbPort); // Database connection

    $res = $connection->query($query);

    return $res;
}

/**
 * Takes an array of queries and executes them in the database
 * @param $database
 * @param array $query
 * @return array
 */
function runSqlQueryArray($database, $query)
{
    $result = array(); //Array to store the output of the database

    $connection = new mysqli($database->dbHost, $database->dbUsername, $database->dbPassword, $database->dbName, $database->dbPort); // Database connection

    // Loops through every query and runs it.
    foreach ($query as $item) {
        $result[] = $connection->query($item);
    }

    return $result;
}

/* <------------- End of the functions ----------------------> */

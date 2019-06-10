<?php

// ---------- NEEDED VALUES ----------
// contains the auth check function
require_once("auth_check_helper.php");
// Database Info
define("DB_HOST", "hidden host");
define("DB_DATABASE", "hidden db");
define("DB_USER", "hidden user");
define("DB_PASS", "hidden password");
// error codes
define("FINE", 0);
define("WRONG_AUTH", 1);
define("MISSING_INFO", 2);
define("WRONG_ACTION", 3);
define("DB_ERROR", 4);
define("MODULE_ERROR", 5);
date_default_timezone_set("America/Chicago");


function returnMessage($error, $status, $code, $mess = "UNDEFINED") {

    if ($mess == "UNDEFINED") {
        $data = [
            "error" => $error,
            "status" => $status,
            "code" => $code
        ];
    } else {
        $data = [
            "error" => $error,
            "status" => $status,
            "code" => $code,
            "description" => $mess
        ];
    }

    header('Content-Type: application/json');
    return json_encode($data);
}

// ------------------------------------------
// things are started here
// ------------------------------------------

if (isset($_REQUEST) && $_REQUEST["auth"]) {
    // check the auth code, auth code is defined at the beginning
    if (apiAuthCheck($_REQUEST["auth"])) {
        $action = $_REQUEST["action"];
        $module = $_REQUEST["module"];

        $inputJSON = file_get_contents('php://input');
        $inputBody= json_decode($inputJSON, true);
        $bodyLogTxt = "Received message for " . $action . " in " . $module . " module:\n" . $inputJSON;
        writeLog($bodyLogTxt);

        // switch actions
        switch ($action) {
            case "create":
                createPlay($module, $inputBody);
                $message = returnMessage("None", "success", FINE, "Create in " . $module . " succeed.");
                echo $message;
                break;
            case "update":
                updatePlay($module, $inputBody);
                $message = returnMessage("None", "success", FINE, "Update in " . $module . " succeed.");
                echo $message;
                break;
            case "delete":
                deletePlay($module, $inputBody);
                $message = returnMessage("None", "success", FINE, "Delete in " . $module . " succeed.");
                echo $message;
                break;
            default:
                // not defined action
                $message = returnMessage("Unknown action", "error", WRONG_ACTION);
                echo $message;
        }
    } else {
        $message = returnMessage("Invalid auth code", "error", WRONG_AUTH);
        writeLog("Someone (IP " . $_SERVER["REMOTE_ADDR"] . ", ADDR " . $_SERVER["SERVER_NAME"] . ") used code [" . $_REQUEST["auth"] . "] for verification and failed.");
        echo $message;
    }
} else {
    // accessing the script directly, record and stop.
    echo "No Direct Access Permitted.";
    writeLog("Someone (IP " . $_SERVER["REMOTE_ADDR"] . ", ADDR " . $_SERVER["SERVER_NAME"] . ") was trying to access the script directly and failed.");
}

// ------------------------------------------
// things are ended here, below are helper functions
// ------------------------------------------

function writeLog($logMessage) {
    // log function, unable to show
}

function dbQuery($query) {
    writeLog("Record Query:\n" . $query);

    $connectionInfo = array( "Database"=>DB_DATABASE, "UID"=>DB_USER, "PWD"=>DB_PASS);
    $dbConnection = sqlsrv_connect(DB_HOST, $connectionInfo);

    if ($dbConnection) {
        $queryResult = sqlsrv_query($dbConnection, $query) or writeLog("DB CANNOT PERFORM QUERY");
        $rowsAffected = sqlsrv_rows_affected($queryResult);
        $dataRow = sqlsrv_fetch_array($queryResult);

        if ($queryResult == false or $rowsAffected == false) {
            // no result
            writeLog("No Result returned for Query " . $query);
            $message = returnMessage("SQL Query No Result", "error", DB_ERROR,
                "The query you passed in (" . $query . ") does not return any result after executing.");
            echo $message;
            exit();
        }

        sqlsrv_free_stmt($dbConnection);
        sqlsrv_close($dbConnection);

        return $dataRow;
    } else {
        writeLog("DB ERROR NO CONNECTION\n" . sqlsrv_errors());
        $message = returnMessage("Database error", "error", DB_ERROR, "Please try again later.");
        echo $message;
        exit();
    }
}

function createPlay($module, $jsonData) {
    $globalZohoId = $jsonData["zohoUId"];

    switch ($module) {
        case "contacts":
            // all things passed in
            $contact_cid = $jsonData["cid"];
            if ($contact_cid == null)
                $contact_cid = readIdFromDB("tblContacts");
            $contact_fn = $jsonData["fn"];
            $contact_ln = $jsonData["ln"];
            $contact_add1 = $jsonData["add1"];
            $contact_add2 = $jsonData["add2"];
            $contact_sal = $jsonData["sal"];
            $contact_city = $jsonData["city"];
            $contact_state = $jsonData["state"];
            $contact_zip = $jsonData["zip"];
            $contact_phone1 = $jsonData["phone1"];
            $contact_phone1Ext = $jsonData["phone1Ext"];
            $contact_phone1TypeId = $jsonData["phone1TypeId"];
            $contact_email = $jsonData["email"];
            $contact_contactTypeId = $jsonData["contactTypeId"];
            $contact_giftLevelId = $jsonData["giftLevelId"];
            $contact_birthday = $jsonData["birthday"];
            $contact_createBy = $jsonData["createBy"];
            // unknown value
            // $contact_createDate = $jsonData["createDate"];
            //$contact_createTime = convertDate($jsonData["createTime"]);
            $contact_createTime = date("Y-m-d h:i:sa");
            // this is the Zoho shipto id, no need in this script
            // $contact_shiptoId = $jsonData["shiptoId"];
            $contact_accNumber = $jsonData["accNumber"];
            $contact_accCreateTime = date("Y-m-d h:i:sa");
            $contact_accCreateByName = $jsonData["accCreateByName"];
            $contact_offsite = $jsonData["offsite"];
            $contact_primaryContact = $jsonData["primaryContact"];

            // insert into tblContacts table
            $tblContactsQuery = "INSERT INTO tblContacts
                                (ContactId, ContactFirst, ContactLast, ContactSalutation, Address1, Address2, City,
                                 State, Zip, Phone1, Phone1TypeId, Phone1Ext, Email, ContactTypeId, GiftLevelId, ContactBirthday,
                                 CreateEmployee, CreateDate, ZohoContactId)
                                 VALUES
                                 ('$contact_cid', '$contact_fn', '$contact_ln', '$contact_sal', '$contact_add1', '$contact_add2', '$contact_city',
                                  '$contact_state', '$contact_zip', '$contact_phone1', '$contact_phone1TypeId', '$contact_phone1Ext',
                                  '$contact_email', '$contact_contactTypeId', '$contact_giftLevelId', '$contact_birthday', '$contact_createBy',
                                  '$contact_createTime', '$globalZohoId')";

            // execute the query
            $tblContactsQueryResult = dbQuery($tblContactsQuery);
            writeLog("SQL INSERT INTO tblContacts completed, returned message:\n" . $tblContactsQueryResult);

            if ($contact_accNumber && !checkNull($contact_accNumber)) {
                $contactByShipToId = readIdFromDB("tblContactByShipTo");
                $tblContactByShipToQuery = "INSERT INTO tblContactByShipTo
                                            (ContactByShipToId, ContactId, ShipToId, OffSite, PrimaryContact,
                                             CreateEmployee, CreateDate)
                                             VALUES
                                             ('$contactByShipToId', '$contact_cid', '$contact_accNumber', '$contact_offsite',
                                              '$contact_primaryContact', '$contact_accCreateByName', '$contact_accCreateTime')";

                // execute the query
                $tblContactByShipToQueryResult = dbQuery($tblContactByShipToQuery);
                writeLog("SQL INSERT INTO tblContactByShipTo completed, returned message:\n" . $tblContactByShipToQueryResult);
            }

            break;
        case "shipto":
            // all things passed in
            $shipTo_accNumber = $jsonData["accNumber"];
            if ($shipTo_accNumber == null || $shipTo_accNumber == "0")
                $shipTo_accNumber = readIdFromDB("tblShipToByBillTo");
            $shipTo_name = $jsonData["shiptoName"];
            $shipTo_add1 = $jsonData["add1"];
            $shipTo_city = $jsonData["city"];
            $shipTo_state = $jsonData["state"];
            $shipTo_zip = $jsonData["zip"];
            $shipTo_createBy = $jsonData["createBy"];
            //$shipTo_createTime = convertDate($jsonData["createTime"]);
            $shipTo_createTime = date("Y-m-d h:i:sa");
            $shipTo_customerId = $jsonData["customerId"];

            // insert into tblShipTo table
            $tblShipToQuery = "INSERT INTO tblShipTo
                               (ShipToId, ShipToName, ShipToAddress1, ShipToCity, ShipToState, ShiptToZip,
                                CreateEmployee, CreateDate, ZohoShipToId)
                                VALUES
                                ('$shipTo_accNumber', '$shipTo_name', '$shipTo_add1', '$shipTo_city', '$shipTo_state', '$shipTo_zip',
                                 '$shipTo_createBy', '$shipTo_createTime', '$globalZohoId')";

            // execute the query
            $tblShipToQueryResult = dbQuery($tblShipToQuery);
            writeLog("SQL INSERT INTO tblShipTo completed, returned message:\n" . $tblShipToQueryResult);

            if ($shipTo_customerId && !checkNull($shipTo_customerId)) {
                $tblShipToByBillToQuery = "BEGIN
                                            IF EXISTS (SELECT ShipToStatus FROM tblShipToByBillTo WHERE ShipToId = '$shipTo_accNumber')
                                                BEGIN
                                                    UPDATE  tblShipToByBillTo
                                                    SET CustomerId = '$shipTo_customerId'
                                                    WHERE ShipToId = '$shipTo_accNumber'
                                                END
                                            ELSE
                                                BEGIN
                                                   INSERT INTO tblShipToByBillTo (CustomerId, ShipToId, ShipToStatus)
                                                   VALUES ( '$shipTo_customerId', '$shipTo_accNumber', '1')
                                                END
                                            END";
                // execute the query
                $tblShipToByBillToQueryResult = dbQuery($tblShipToByBillToQuery);
                writeLog("SQL INSERT INTO tblShipToByBillTo completed, returned message:\n" . $tblShipToByBillToQueryResult);
            }
            break;
        case "billto":
            // holiday only int
            $billTo_holiday = 0;
            // all things passed in
            $billTo_customerId = $jsonData["customerId"];
            if ($billTo_customerId == null || $billTo_customerId == "0")
                $billTo_customerId = readIdFromDB("tblCustomers");
            $billTo_contractDate = $jsonData["contractDate"];
            $billTo_contractTerm = $jsonData["contractTerm"];
            $billTo_billingCycle = $jsonData["billingCycle"];
            $billTo_name = $jsonData["billtoName"];
            $billTo_add1 = $jsonData["billtoAdd1"];
            $billTo_city = $jsonData["billtoCity"];
            $billTo_state = $jsonData["billtoState"];
            $billTo_zip = $jsonData["billtoZip"];
            //$billTo_createTime = convertDate($jsonData["createTime"]);
            $billTo_createTime = date("Y-m-d h:i:sa");
            $billTo_createBy = $jsonData["createBy"];
            $billTo_accManager = $jsonData["accManager"];
            $billTo_division = $jsonData["division"];

            if (stripos($billTo_name, "holiday") !== false) {
                $billTo_holiday = -1;
            }

            // insert into tblCustomers table
            $tblCustomersQuery = "INSERT INTO tblCustomers
                                  (CustomerId, ContractDate, ContractTerm, BillingCycle, HolidayOnly, CustomerBillTo1Name,
                                   CustomerBillTo1Address1, CustomerBillTo1City, CustomerBillTo1State, CustomerBillTo1Zip,
                                   CreateEmployee, CreateDate, AccountManager, ZohoBillToId, DivisionId)
                                   VALUES
                                   ('$billTo_customerId', '$billTo_contractDate', '$billTo_contractTerm', '$billTo_billingCycle',
                                   '$billTo_holiday', '$billTo_name', '$billTo_add1', '$billTo_city', '$billTo_state', '$billTo_zip',
                                    '$billTo_createBy', '$billTo_createTime', '$billTo_accManager', '$globalZohoId', '$billTo_division')";

            // execute the query
            $tblCustomersQueryResult = dbQuery($tblCustomersQuery);
            writeLog("SQL INSERT INTO tblCustomers completed, returned message:\n" . $tblCustomersQueryResult);

            break;
        default:
            writeLog("A requested was received for " . $module . " module, and the system didn't recognize it.");
            $message = returnMessage("Module error", "error", MODULE_ERROR, "Unknown or unsupported module: " . $module . ".");
            echo $message;
            exit();
    }
}

function updatePlay($module, $jsonData) {
    // Retrieve record Zoho Id.
    $globalZohoId = $jsonData["zohoUId"];

    switch ($module) {
        case "contacts":
            // all things passed in
            $contact_cid = $jsonData["cid"];
            $contact_fn = $jsonData["fn"];
            $contact_ln = $jsonData["ln"];
            $contact_add1 = $jsonData["add1"];
            $contact_add2 = $jsonData["add2"];
            $contact_sal = $jsonData["sal"];
            $contact_city = $jsonData["city"];
            $contact_state = $jsonData["state"];
            $contact_zip = $jsonData["zip"];
            $contact_phone1 = $jsonData["phone1"];
            $contact_phone1Ext = $jsonData["phone1Ext"];
            $contact_phone1TypeId = $jsonData["phone1TypeId"];
            $contact_email = $jsonData["email"];
            $contact_contactTypeId = $jsonData["contactTypeId"];
            $contact_giftLevelId = $jsonData["giftLevelId"];
            $contact_birthday = $jsonData["birthday"];
            $contact_createBy = $jsonData["createBy"];
            // unknown value
            // $contact_createDate = $jsonData["createDate"];
            //$contact_createTime = convertDate($jsonData["createTime"]);
            // this is the Zoho shipto id, no need in this script
            // $contact_shiptoId = $jsonData["shiptoId"];
            $contact_accNumber = $jsonData["accNumber"];
            $contact_accCreateTime = convertDate($jsonData["accCreateTime"]);
            //$contact_accCreateByName = $jsonData["accCreateByName"];
            $contact_offsite = $jsonData["offsite"];
            $contact_primaryContact = $jsonData["primaryContact"];

            // update into tblContacts table
            $tblContactsQuery = "UPDATE tblContacts SET
                       ContactFirst = '$contact_fn',
                       ContactLast = '$contact_ln',
                       ContactSalutation = '$contact_sal',
                       Address1 = '$contact_add1',
                       Address2 = '$contact_add2',
                       City = '$contact_city',
                       State = '$contact_state',
                       Zip = '$contact_zip',
                       Phone1 = '$contact_phone1',
                       Phone1TypeId = '$contact_phone1TypeId',
                       Phone1Ext = '$contact_phone1Ext',
                       Email = '$contact_email',
                       ContactTypeId = '$contact_contactTypeId',
                       GiftLevelId = '$contact_giftLevelId',
                       ContactBirthday = '$contact_birthday',
                       CreateEmployee = '$contact_createBy',
                       ZohoContactId = '$globalZohoId'
                       WHERE
                       ContactId = '$contact_cid'";

            // execute the query
            $tblContactsQueryResult = dbQuery($tblContactsQuery);
            writeLog("SQL UPDATE tblContacts completed, returned message:\n" . $tblContactsQueryResult);

            if ($contact_accNumber && !checkNull($contact_accNumber)) {
                $tblContactByShipToQuery = "UPDATE tblContactByShipTo SET
                              ShipToId = '$contact_accNumber',
                              OffSite = '$contact_offsite',
                              PrimaryContact = '$contact_primaryContact',
                              CreateDate = '$contact_accCreateTime'
                              WHERE
                              ContactId = '$contact_cid'";

                // execute the query
                $tblContactByShipToQueryResult = dbQuery($tblContactByShipToQuery);
                writeLog("SQL UPDATE tblContactByShipTo completed, returned message:\n" . $tblContactByShipToQueryResult);
            }

            break;
        case "shipto":
            // all things passed in
            $shipTo_accNumber = $jsonData["accNumber"];
            $shipTo_name = $jsonData["shiptoName"];
            $shipTo_add1 = $jsonData["add1"];
            $shipTo_city = $jsonData["city"];
            $shipTo_state = $jsonData["state"];
            $shipTo_zip = $jsonData["zip"];
            $shipTo_createBy = $jsonData["createBy"];
            //$shipTo_createTime = convertDate($jsonData["createTime"]);
            $shipTo_customerId = $jsonData["customerId"];

            // update into tblShipTo table
            $tblShipToQuery = "UPDATE tblShipTo SET
                     ShipToName = '$shipTo_name',
                     ShipToAddress1 = '$shipTo_add1',
                     ShipToCity = '$shipTo_city',
                     ShipToState = '$shipTo_state',
                     ShiptToZip = '$shipTo_zip',
                     CreateEmployee = '$shipTo_createBy',
                     ZohoShipToId = '$globalZohoId'
                     WHERE
                     ShipToId = '$shipTo_accNumber'";

            // execute the query
            $tblShipToQueryResult = dbQuery($tblShipToQuery);
            writeLog("SQL UPDATE tblShipTo completed, returned message:\n" . $tblShipToQueryResult);

            if ($shipTo_customerId && !checkNull($shipTo_customerId)) {
                $tblShipToByBillToQuery = "BEGIN
                                            IF EXISTS (SELECT ShipToStatus FROM tblShipToByBillTo WHERE ShipToId = '$shipTo_accNumber')
                                                BEGIN
                                                    UPDATE  tblShipToByBillTo
                                                    SET CustomerId = '$shipTo_customerId'
                                                    WHERE ShipToId = '$shipTo_accNumber'
                                                END
                                            ELSE
                                                BEGIN
                                                   INSERT INTO tblShipToByBillTo (CustomerId, ShipToId, ShipToStatus)
                                                   VALUES ( '$shipTo_customerId', '$shipTo_accNumber', '1')
                                                END
                                            END";
                // execute the query
                $tblShipToByBillToQueryResult = dbQuery($tblShipToByBillToQuery);
                writeLog("SQL UPDATE tblShipToByBillTo completed, returned message:\n" . $tblShipToByBillToQueryResult);
            }
            break;
        case "billto":
            // holiday only int
            $billTo_holiday = 0;
            // all things passed in
            $billTo_customerId = $jsonData["customerId"];
            $billTo_contractDate = $jsonData["contractDate"];
            $billTo_contractTerm = $jsonData["contractTerm"];
            $billTo_billingCycle = $jsonData["billingCycle"];
            $billTo_name = $jsonData["billtoName"];
            $billTo_add1 = $jsonData["billtoAdd1"];
            $billTo_city = $jsonData["billtoCity"];
            $billTo_state = $jsonData["billtoState"];
            $billTo_zip = $jsonData["billtoZip"];
            //$billTo_createTime = convertDate($jsonData["createTime"]);
            $billTo_createBy = $jsonData["createBy"];
            $billTo_accManager = $jsonData["accManager"];
            $billTo_division = $jsonData["division"];

            if (stripos($billTo_name, "holiday") !== false) {
                $billTo_holiday = -1;
            }

            // update tblCustomers table
            $tblCustomersQuery = "UPDATE tblCustomers SET
                        ContractDate = '$billTo_contractDate',
                        ContractTerm = '$billTo_contractTerm',
                        BillingCycle = '$billTo_billingCycle',
                        HolidayOnly = '$billTo_holiday',
                        CustomerBillTo1Name = '$billTo_name',
                        CustomerBillTo1Address1 = '$billTo_add1',
                        CustomerBillTo1City = '$billTo_city',
                        CustomerBillTo1State = '$billTo_state',
                        CustomerBillTo1Zip = '$billTo_zip',
                        CreateEmployee = '$billTo_createBy',
                        AccountManager = '$billTo_accManager',
                        ZohoBillToId = '$globalZohoId',
                        DivisionId = '$billTo_division'
                        WHERE
                        CustomerId = '$billTo_customerId'";

            // execute the query
            $tblCustomersQueryResult = dbQuery($tblCustomersQuery);
            writeLog("SQL UPDATE tblCustomers completed, returned message:\n" . $tblCustomersQueryResult);

            break;
        default:
            writeLog("A requested was received for " . $module . " module, and the system didn't recognize it.");
            $message = returnMessage("Module error", "error", MODULE_ERROR, "Unknown or unsupported module: " . $module . ".");
            echo $message;
            exit();
    }
}

function deletePlay($module, $jsonData) {

    // all things passed in
    $deleteId = $jsonData["id"];
    $deleted = date("Y-m-d h:i:sa");
    $deleteBy = $jsonData["modifiedBy"];
    $deleteById = searchPeopleInDb($deleteBy);
    $deleteTime = convertDate($jsonData["modifiedTime"]);

    switch ($module) {
        case "contacts":
            $query = "UPDATE tblContacts SET Deleted = '$deleted', ModifyEmployee = '$deleteById', ModifyDate = '$deleteTime' WHERE ContactId = '$deleteId'";
            break;
        case "shipto":
            $query = "UPDATE tblShipTo SET Deleted = '$deleted', ModifyEmployee = '$deleteById', ModifyDate = '$deleteTime' WHERE ShipToId = '$deleteId'";
            break;
        case "billto":
            $query = "UPDATE tblCustomers SET Deleted = '$deleted', ModifyEmployee = '$deleteById', ModifyDate = '$deleteTime' WHERE CustomerId = '$deleteId'";
            break;
        default:
            writeLog("A requested was received for " . $module . " module, and the system didn't recognize it.");
            $message = returnMessage("Module error", "error", MODULE_ERROR, "Unknown or unsupported module: " . $module . ".");
            echo $message;
            exit();
    }

    if ($query != "") {
        $result = dbQuery($query);
        writeLog("SQL DELETE in " . $module . " completed, returned message:\n" . $result);
    }
}

function checkNull($value) {
    if ($value == null || strcasecmp($value, "NULL") == 0) {
        return true;
    } else {
        return false;
    }
}

function readIdFromDB($tableName) {
    $query = "SELECT Counter FROM tblCounters WHERE CounterTable='$tableName'";
    $result = dbQuery($query);
    $newId = intval($result[0]);
    $nextId = $newId + 1;
    $query2 = "UPDATE tblCounters SET Counter='$nextId' WHERE CounterTable='$tableName'";
    $resultUpdate = dbQuery($query2);
    return $newId;
}

function searchPeopleInDb($name) {
    $nameArray = explode(" ", $name);
    $fn = strtolower($nameArray[0]);
    $ln = strtolower($nameArray[1]);

    $searchQuery = "SELECT DISTINCT EmployeeId FROM tblEmployee WHERE lower(FirstName)='$fn' AND lower(LastName)='$ln'";
    // searchResult contains employee id(s)
    $searchResult = dbQuery($searchQuery);
    $id = $searchResult[0];

    return $id;
}

function convertDate($date) {
    try {
        $arr = explode("T", $date);
        $time = explode("-", $arr[1])[0];
        return $arr[0] . " " . $time;
    } catch (Exception $e) {
        return $date;
    }
}
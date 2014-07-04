<?php

/**
 * Class ImageMapping
 *
 * Mapping images with correct slots
 * @author: Awlad H.<awlad@nascenia.com>
 * @author: Mostafij R.<mostafij@nascenia.com>
 *
 */
class ImageMapping
{

    public $objPdo;
    public $host = 'localhost';
    public $dbname = 'casino_mapping';
    public $user = 'root';
    public $pass = 'root';
    public $intCount = 0;

    /**
     *
     */
    public function __construct()
    {
        try {

            $this->objPdo = new PDO("mysql:host=$this->host;dbname=$this->dbname", $this->user, $this->pass);
            $this->objPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die('could not connected! ' . $e->getMessage());
        }
    }


    /**
     * @desc get array of slots from csv
     *
     * @return array
     */

    public function generateSlotArray()
    {

        $objCsvFile = new SplFileObject('slot-list.csv', 'r');
        $arrSlotInfos = array();
        while (!$objCsvFile->eof()) {
            $arrSlotInfos[] = $objCsvFile->fgetcsv();
        }

        return $arrSlotInfos;
    }

    /**
     * @return array
     */
    public function generateMissingSlotArray()
    {

        $objCsvFile = new SplFileObject('missing-slots-refactor.csv', 'r');
        $arrSlotInfos = array();
        while (!$objCsvFile->eof()) {
            $arrSlotInfos[] = $objCsvFile->fgetcsv();
        }

        return $arrSlotInfos;
    }


    /**
     * @return array
     */

    function generateSqlDump()
    {

        $arrRightSlots = $this->generateSlotArray();
        $arrMissedSlots = $this->generateMissingSlotArray();

        $sql = 'SELECT `name`, `image_screenshot_1`, `image_screenshot_2`, `image_screenshot_3`, `image_symbol_wild`, `image_symbol_scatter`, `image_screenshot_paylines`, `image_screenshot_bonusround`, `slot_type_fk`
         FROM `slots`
         ';
        $objSt = $this->objPdo->prepare($sql);
        $objSt->execute();
        $objSt->setFetchMode(PDO::FETCH_ASSOC);
        $strOutput = '';

        while ($row = $objSt->fetch()) {
            $strSlotName = $row['name'];

            $blnInList = false;
            foreach ($arrRightSlots as $arrRightSlot) {
                if (isset($arrRightSlot[1]) && !empty($arrRightSlot[0]) && $this->matchName(
                        $strSlotName,
                        $arrRightSlot[1]
                    )
                ) {
                    $strOutput .= $this->createSql($arrRightSlot[0], $row);
                    $blnInList = true;
                }
            }
            if (!$blnInList) {
                foreach ($arrMissedSlots as $arrMissedSlot) {
                    if (isset($arrMissedSlot[0]) && !empty($arrMissedSlot[0]) && $arrMissedSlot[0] != null && $arrMissedSlot[0] != 'NULL' && $this->matchName(
                            $strSlotName,
                            $arrMissedSlot[1]
                        )
                    ) {
                        $strOutput .= $this->createSql($arrMissedSlot[0], $row);
                        echo ' Missed slot name:  ' . $arrMissedSlot[1] . '<br> ';
                    }
                }
            }

        }

        echo 'Total Row : ' . $this->intCount . ' Generated !<br> ';
        return $strOutput;
    }

    /**
     * @param $intSlotId
     * @param $arrSlot
     * @return string
     */


    public function createSql($intSlotId, $arrSlot)
    {

        $arrImageType = array(
            'image_screenshot_1' => 2,
            'image_screenshot_2' => 2,
            'image_screenshot_3' => 2,
            'image_symbol_wild' => 7,
            'image_symbol_scatter' => 8,
            'image_screenshot_paylines' => 3,
            'image_screenshot_bonusround' => 5
        );

        $strOutput = PHP_EOL . 'INSERT INTO `values_slot_image` (`values_slot_fk`, `common_image_type_fk`, `text`, `data`) VALUES ';

        foreach ($arrImageType as $strKey => $strValue) {

            if (isset($arrSlot[$strKey]) && $arrSlot[$strKey] != null && $arrSlot[$strKey] != 'NULL') {
                $this->intCount++;
                $strImage = $this->formatImageString($arrSlot[$strKey]);
                $strOutput .= PHP_EOL . '(' . $intSlotId . ',' . $arrImageType[$strKey] . ',NULL,' . "'$strImage'" . '),';
            }
        }
        $strOutput = substr_replace($strOutput, ';', -1);

        return $strOutput;

    }

    /**
     * @param $str
     * @return string
     */

    public function formatImageString($str)
    {

        $imgData = base64_decode($str);
        $f = finfo_open();
        $mime_type = finfo_buffer($f, $imgData, FILEINFO_MIME_TYPE);
        $strReturn = 'data:' . $mime_type . ';base64,' . $str;

        return $strReturn;
    }

    /**
     * @param $strSlotName
     * @param $strCsvSlotName
     * @return bool
     */
    public function matchName($strSlotName, $strCsvSlotName)
    {

        $strSlotName = strtolower(trim($strSlotName));
        $strCsvSlotName = strtolower(trim($strCsvSlotName));
        //case 1: direct match
        if ($strSlotName == $strCsvSlotName) {
            return true;
        }
        //case 2: alphanumeric  match
        if (preg_replace("/[^A-Za-z0-9]/", '', $strSlotName) == preg_replace(
                "/[^A-Za-z0-9]/",
                '',
                $strCsvSlotName
            )
        ) {
            return true;
        }
        //case 3: replace and, slots  match
        if (str_replace(array('and', 'slots'), '', preg_replace("/[^A-Za-z0-9]/", '', $strCsvSlotName)) == str_replace(
                array('and', 'slots'),
                '',
                preg_replace("/[^A-Za-z0-9]/", '', $strSlotName)
            )
        ) {
            return true;
        }

        return false;

    }


}

ini_set('memory_limit', '-1');
ini_set('max_execution_time', 300);
$objImg = new ImageMapping();
$strSql = $objImg->generateSqlDump();
file_put_contents('values_slot_imageNew.sql', $strSql);
echo ' Done :)';
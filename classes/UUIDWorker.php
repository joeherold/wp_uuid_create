<?php

/**
 * If Contao was updated to >3.2 and the singleSRC and multiSCR values where
 * not changed from an numeric (integer) value to the new UUID an so all the
 * connections to the files are gone in backend and frontend, this runonce.php
 * will correct it and will replace the integers in the binary(16) fields to
 * an UUID.
 * 
 * Therefore it looks up for all fields with input-type fileTree and executes
 * on these fields only.
 *
 * @author johannespichler
 */

namespace UUIDCreator;

class UUIDWorker extends \Contao\Controller {

    public $logString = '';
    public $logStringErrors = '';

    public function __construct() {
        parent::__construct();
    }

    public function run() {
        
        $this->import('Database');
        
        //repair the tl_files table
        if ((\Input::get('repair') == 'tl_files')) {
            return $this->repairFileSystemDatabase();
        }
        
        //create uuids for the other tables
        if ((\Input::get('create') != '')) {
            return $this->updateFileTreeFields();
        }
    }

    /**
     * Update all FileTree fields
     */
    public function updateFileTreeFields() {

        $this->logString .= 'initated...';
        $arrFiles = array();


        foreach (scan(TL_ROOT . '/system/modules') as $strModule) {
            $strDir = 'system/modules/' . $strModule . '/dca';

            if (!is_dir(TL_ROOT . '/' . $strDir)) {
                continue;
            }

            foreach (scan(TL_ROOT . '/' . $strDir) as $strFile) {
                // Ignore non PHP files and files which have been included before
                if (substr($strFile, -4) != '.php' || in_array($strFile, $arrFiles)) {
                    continue;
                }

                $arrFiles[] = substr($strFile, 0, -4);
            }
        }

        $arrFields = array();

        // Find all fileTree fields
        foreach ($arrFiles as $strTable) {
            try {
                $this->loadDataContainer($strTable);
            } catch (\Exception $e) {
                $this->logStringErrors .= '<div class="tl_red">' . $e->getMessage() . '</div>';
                continue;
            }

            $arrConfig = &$GLOBALS['TL_DCA'][$strTable]['config'];

            // Skip non-database DCAs
            if ($arrConfig['dataContainer'] == 'File') {
                continue;
            }
            if ($arrConfig['dataContainer'] == 'Folder' && !$arrConfig['databaseAssisted']) {
                continue;
            }

            // Make sure there are fields (see #6437)
            if (is_array($GLOBALS['TL_DCA'][$strTable]['fields'])) {
                foreach ($GLOBALS['TL_DCA'][$strTable]['fields'] as $strField => $arrField) {
                    // FIXME: support other field types
                    if ($arrField['inputType'] == 'fileTree') {
                        if ($this->Database->fieldExists($strField, $strTable, true)) {
                            $key = $arrField['eval']['multiple'] ? 'multiple' : 'single';
                            $arrFields[$key][] = $strTable . '.' . $strField;
                        }

                        // Convert the order fields as well
                        if (isset($arrField['eval']['orderField']) && isset($GLOBALS['TL_DCA'][$strTable]['fields'][$arrField['eval']['orderField']])) {
                            if ($this->Database->fieldExists($arrField['eval']['orderField'], $strTable, true)) {
                                $arrFields['order'][] = $strTable . '.' . $arrField['eval']['orderField'];
                            }
                        }
                    }
                }
            }
        }


        // Update the existing singleSRC entries
        if (isset($arrFields['single']) && (\Input::get('create') == 'singleSRC')) {
            $this->logString = '<h3>SINGLE SRC</h3>';
            foreach ($arrFields['single'] as $val) {
                list($table, $field) = explode('.', $val);

                $this->convertSingleField($table, $field);
            }
        }

        // Update the existing multiSRC entries
        if (isset($arrFields['multiple']) && (\Input::get('create') == 'multiSRC')) {
            $this->logString = '<h3>MULTI SRC</h3>';
            foreach ($arrFields['multiple'] as $val) {
                list($table, $field) = explode('.', $val);
                $this->convertMultiField($table, $field);
            }
        }




        return true;
    }

    public function repairFileSystemDatabase() {
        // Check whether there are UUIDs
        if (!$this->Database->fieldExists('uuid', 'tl_files')) {
            try {
                if (!$this->Database->fieldExists('uuid', 'tl_files')) {
                    // Adjust the DB structure
                    $this->Database->query("ALTER TABLE `tl_files` ADD `uuid` binary(16) NULL");
                    $this->Database->query("ALTER TABLE `tl_files` ADD UNIQUE KEY `uuid` (`uuid`)");
                }
            } catch (\Exception $e) {
                $this->logStringErrors .= '<div class="tl_red">' . $e->getMessage() . '</div>';
                $this->logString .= '<br><strong class="tl_red">ERROR on updating with new UUID value</strong>';
            }

            try {
                if (!$this->Database->fieldExists('pid_backup', 'tl_files')) {
                    // Backup the pid column and change the column type
                    $this->Database->query("ALTER TABLE `tl_files` ADD `pid_backup` int(10) unsigned NOT NULL default '0'");
                    $this->Database->query("UPDATE `tl_files` SET `pid_backup`=`pid`");
                    $this->Database->query("ALTER TABLE `tl_files` CHANGE `pid` `pid` binary(16) NULL");
                    $this->Database->query("UPDATE `tl_files` SET `pid`=NULL");
                    $this->Database->query("UPDATE `tl_files` SET `pid`=NULL WHERE `pid_backup`=0");
                }
            } catch (\Exception $e) {
                $this->logStringErrors .= '<div class="tl_red">' . $e->getMessage() . '</div>';
                $this->logString .= '<br><strong class="tl_red">ERROR on creating tl_files.pid_backup an setting values</strong>';
            }

            /*
             * first we will create all UUIDs in tl_files.uuid
             */
            $objFiles = $this->Database->query("SELECT id FROM tl_files");

            // Generate the UUIDs
            while ($objFiles->next()) {
                $this->Database->prepare("UPDATE tl_files SET uuid=? WHERE id=?")
                        ->execute($this->Database->getUuid(), $objFiles->id);
            }

            $objFiles = $this->Database->query("SELECT pid_backup FROM tl_files WHERE pid_backup>0 GROUP BY pid_backup");


            /*
             * next we will create all pid.UUIDs in tl_files.pid
             */
            // Adjust the parent IDs
            while ($objFiles->next()) {
                if (($objFiles->pid_backup) > 0) {
                    $objParent = $this->Database->prepare("SELECT uuid FROM tl_files WHERE id=?")
                            ->execute($objFiles->pid_backup);

                    if ($objParent->numRows < 1) {

                        $this->logStringErrors .= '<div class="tl_red">' . $e->getMessage() . '</div>';
                        $this->logString .= '<br><strong class="tl_red">Invalid parent ID ' . $objFiles->pid_backup . '</strong>';
                        continue;
                    }

                    $this->Database->prepare("UPDATE tl_files SET pid=? WHERE pid_backup=?")
                            ->execute($objParent->uuid, $objFiles->pid_backup);
                }
            }



            // Drop the pid_backup column
            $this->Database->query("ALTER TABLE `tl_files` DROP `pid_backup`");
        }
        return true;
    }

    /**
     * Convert a single source field to UUIDs
     *
     * @param string $table The table name
     * @param string $field The field name
     */
    public function convertSingleField($table, $field) {
        $this->logString .= '<br>--------<br>affected Table: <strong>' . $table . '</strong> and Field: <strong>' . $field . '</strong>';

        $backup = $field . '_backup';


        $objDatabase = \Contao\Database::getInstance();
        // Backup temporarly the original column and then change the column type
        if (!$objDatabase->fieldExists($backup, $table, true)) {
            try {
                $objDatabase->query("ALTER TABLE `$table` ADD `$backup` varchar(255) NOT NULL default ''");
                $this->logString .= '<br>Field: <strong>' . $backup . '</strong> <span class="tl_green">added</span>.';
                $objDatabase->query("ALTER TABLE `$table` CHANGE `$field` `$field` binary(16) NULL");
                $this->logString .= '<br>Field: <strong>' . $field . '</strong> <span class="tl_blue">converted</span> to binary(16).';
            } catch (\Exception $e) {
                $this->logStringErrors .= '<div class="tl_red">' . $e->getMessage() . '</div>';
                $this->logString .= '<br><strong class="tl_red">ERROR on creating backup table-field and field conversion to binary(16)...</strong>';
            }
        }
        if ($objDatabase->query("UPDATE `$table` SET `$backup` = '_'")) {
            $this->logString .= '<br>...Field ' . $backup . ' prepared.';
        }



        $objRow = $objDatabase->query("SELECT id, $field, $backup FROM $table WHERE $backup !=''");
        if ($objRow) {
            $this->logString .= '<br>...checking ' . $table . ' records for wrong varchar/binary values';
            while ($objRow->next()) {
                if (($objRow->$field) > 0) {
                    $this->logString .= '<br>wrong value found.';
                    $objFile = \FilesModel::findById(intval($objRow->$field));
                    if ($objFile) {
                        try {
                            $objDatabase->prepare("UPDATE $table SET $field=? WHERE id=?")
                                    ->execute($objFile->uuid, $objRow->id);
                            $this->logString .= '<br>- Record with id <strong class="tl_green">' . $objRow->id . '</strong> updated.';
                        } catch (\Exception $e) {
                            $this->logStringErrors .= '<div class="tl_red">' . $e->getMessage() . '</div>';
                            $this->logString .= '<br><strong class="tl_red">ERROR on updating with new UUID value</strong>';
                        }


                        /* $objDatabase->prepare("UPDATE $table SET $backup=? WHERE id=?")
                          ->execute($objRow->$field, $objRow->id); */
                    } else {
                        $this->logString .= '<br>Can not find any File in tl_files according to given ID';
                    }
                }
            }
            $this->logString .= '<br>...done';
        }
        $objDatabase->query("ALTER TABLE `$table` DROP `$backup` ");
        $this->logString .= '<br>Field: <strong>' . $backup . '</strong> <span class="tl_red">removed</span>.';
    }

    /**
     * Convert a multi source field to UUIDs
     *
     * @param string $table The table name
     * @param string $field The field name
     */
    public function convertMultiField($table, $field) {
        $this->logString .= '<br>--------<br>affected Table: <strong>' . $table . '</strong> and Field: <strong>' . $field . '</strong>';
        $backup = $field . '_backup';
        $objDatabase = \Database::getInstance();

        // Backup temporarly the original column and then change the column type
        if (!$objDatabase->fieldExists($backup, $table, true)) {
            try {
                $objDatabase->query("ALTER TABLE `$table` ADD `$backup` blob NULL");
                $this->logString .= '<br>Field: <strong>' . $backup . '</strong> <span class="tl_green">added</span>.';
                $objDatabase->query("ALTER TABLE `$table` CHANGE `$field` `$field` blob NULL");
                $this->logString .= '<br>Field: <strong>' . $field . '</strong> <span class="tl_blue">converted</span> to blob.';
            } catch (\Exception $e) {
                $this->logStringErrors .= '<div class="tl_red">' . $e->getMessage() . '</div>';
                $this->logString .= '<br><strong class="tl_red">ERROR on creating backup table-field and field conversion to blob...</strong>';
            }
        }
        if ($objDatabase->query("UPDATE `$table` SET `$backup` = '_'")) {
            $this->logString .= '<br>...Field ' . $backup . ' prepared.';
        }

        $objRow = $objDatabase->query("SELECT id, $field, $backup FROM $table WHERE $backup!=''");
        $found = 0;
        if ($objRow) {
            while ($objRow->next()) {
                $arrPaths = deserialize($objRow->$backup, true);

                if (empty($arrPaths)) {
                    continue;
                }

                foreach ($arrPaths as $k => $v) {

                    if (($v) > 0) {
                        $found +=1;
                        $objFile = \FilesModel::findByPk(intval($v));
                        if ($objFile) {
                            $arrPaths[$k] = $objFile->uuid;
                        } else {
                            $this->logString .= '<br>Can not find any File in tl_files according to given ID';
                        }
                    }
                }
                try {
                    if ($found > 0) {
                        $objDatabase->prepare("UPDATE $table SET $field=? WHERE id=?")
                                ->execute(serialize($arrPaths), $objRow->id);
                        $this->logString .= '<br>- Record with id <strong class="tl_green">' . $objRow->id . '</strong> updated.';
                    }
                } catch (\Exception $e) {
                    $this->logStringErrors .= '<div class="tl_red">' . $e->getMessage() . '</div>';
                    $this->logString .= '<br><strong class="tl_red">ERROR on updating with new UUID value</strong>';
                }
            }
        }
        $objDatabase->query("ALTER TABLE `$table` DROP `$backup` ");
        $this->logString .= '<br>Field: <strong>' . $backup . '</strong> <span class="tl_red">removed</span>.';
    }

}

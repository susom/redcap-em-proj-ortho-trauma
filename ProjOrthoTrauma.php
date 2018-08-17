<?php
namespace Stanford\ProjOrthoTrauma;

use \REDCap;
use \Project;

class ProjOrthoTrauma extends \ExternalModules\AbstractExternalModule
{

    public $survey_record;
    public $main_pk;
    public $main_record;

    function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance = 1 ) {

        // Step 1 - Determine the 'main' record id for the completed survey record
        $q = REDCap::getData('json',array($record));
        $records = json_decode($q, true);
        $survey_record = $records[0];


        // Step 2 - If they used opiates, then no need to continue
        $yes_no_field = $this->getProjectSetting('yes-no-field');
        $yn_val = $survey_record[$yes_no_field];
        if ($yn_val == 1) {
            $this->emDebug("$record used opiates");
            return;
        }


        // Step 3 - get the record_id for the participant in the main project
        $survey_fk_field = $this->getProjectSetting('survey-fk-field');
        $parent_record_id = $survey_record[$survey_fk_field];
        $this->emDebug($survey_record, $survey_fk_field, $parent_record_id);


        // Step 4 - load the 'main' record to see if they have already reached cessation
        $parent_project_id = $this->getProjectSetting('parent-project-id');
        $parent_event_name = $this->getProjectSetting('parent-project-event-name');
        $parent_cessation_field = $this->getProjectSetting('parent-project-cessation-date-field');

        $mainProj = new Project($parent_project_id);
        $main_pk_field = $mainProj->table_pk;

        $q = REDCap::getData($parent_project_id,'json',array($parent_record_id), array($main_pk_field, $parent_cessation_field) , array($parent_event_name));
        $records = json_decode($q,true);
        $main_record = $records[0];
        $cessation_date = $main_record[$parent_cessation_field];

        $this->emDebug($main_record, $main_pk_field, $cessation_date);
        if (!empty($cessation_date)) {
            $this->emLog("Record $parent_record_id has already completed: $cessation_date");
            return;
        }

        // Step 5 - load ALL survey responses for this record
        $filter = "[$survey_fk_field] = '$parent_record_id'";
        $q = REDCap::getData('json', null, null, null, null, false, false, false, $filter);
        $survey_responses = json_decode($q,true);

        // $this->debug($survey_responses);

        // Build an array of day_number -> survey response.
        // Build an array of day_number -> yes/no response.
        $day_number_map = array();
        $day_number_yn = array();
        foreach ($survey_responses as $response) {
            $day_numbber = $response['survey_day_number'];
            $yn_val = $response[$yes_no_field];
            $day_number_map[$day_numbber] = $response;
            $day_number_yn[$day_numbber] = $yn_val;
        }
        ksort($day_number_map);
        ksort($day_number_yn);
        $this->emDebug($day_number_map, $day_number_yn);

        // Step 6 - see if we have reached cessation
        $no_threshold = $this->getProjectSetting('number-of-nos');

        // Start at the beginning
        $last_day = 0;
        $run_count = 0;    // Number of consecutive nos
        foreach ($day_number_yn as $day => $yn) {
            $this->emDebug("At Day $day - Response $yn - Count $run_count - Last day $last_day");

            // Did they report using opioids today
            if ($yn !== "0") {
                $this->emDebug("Not a no");
                $run_count = 0;
            } else {
                // They did not use today

                // detect gaps which restart the run...
                if ($day - $last_day > 1 && $run_count !== 0) {
                    $this->emDebug("Restarting run count at day $day due to " . ($day - $last_day - 1) . " day gap");
                    $run_count = 0;
                }

                $run_count++;
                $this->emDebug("Incrementing run_count to $run_count at day  $day");

                if ($run_count >= $no_threshold) {
                    // We have reached cessation

                    // Get the date for this response
                    $survey_date = $day_number_map[$day]['survey_date'];

                    $this->emLog("Cessation for $parent_record_id reached on $survey_date - day $day - with run of $run_count");

                    $main_record[$parent_cessation_field] = $survey_date;
                    $saveResult = REDCap::saveData($parent_project_id, 'json', json_encode(array($main_record)));
                    $this->emDebug("Save Results", $saveResult, $main_record);

                    // Break out of loop - no need to go further
                    break;
                }
            }

            // Keep last day to detect gaps of more than 1 day
            $last_day = $day;
        }




    }


    function emLog() {
        $emLogger = \ExternalModules\ExternalModules::getModuleInstance('em_logger');
        $emLogger->log($this->PREFIX, func_get_args(), "INFO");
    }

    function emDebug() {
        // Check if debug enabled
        if ($this->getSystemSetting('enable-system-debug-logging') || $this->getProjectSetting('enable-project-debug-logging')) {
            $emLogger = \ExternalModules\ExternalModules::getModuleInstance('em_logger');
            $emLogger->log($this->PREFIX, func_get_args(), "DEBUG");
        }
    }

    function emError() {
        $emLogger = \ExternalModules\ExternalModules::getModuleInstance('em_logger');
        $emLogger->log($this->PREFIX, func_get_args(), "ERROR");
    }

}
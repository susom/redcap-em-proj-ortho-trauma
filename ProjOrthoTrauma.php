<?php
namespace Stanford\ProjOrthoTrauma;

class ProjOrthoTrauma extends \ExternalModules\AbstractExternalModule
{

    function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance = 1 ) {
        $config = $this->getProjectSettings();

        $this->debug($config);

        // "key": "parent_project_id",
        // "key": "parent_project_event_id",
        // "key": "parent_project_cessation_date_field",
        // "key": "number-of-nos",
        // "key": "yes-no-field",
    }


    function log() {
        $emLogger = \ExternalModules\ExternalModules::getModuleInstance('em_logger');
        $emLogger->log($this->PREFIX, func_get_args(), "INFO");
    }

    function debug() {
        // Check if debug enabled
        if ($this->getSystemSetting('enable-system-debug-logging') || $this->getProjectSetting('enable-project-debug-logging')) {
            $emLogger = \ExternalModules\ExternalModules::getModuleInstance('em_logger');
            $emLogger->log($this->PREFIX, func_get_args(), "DEBUG");
        }
    }

    function error() {
        $emLogger = \ExternalModules\ExternalModules::getModuleInstance('em_logger');
        $emLogger->log($this->PREFIX, func_get_args(), "ERROR");
    }

}
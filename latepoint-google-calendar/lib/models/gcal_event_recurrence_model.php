<?php

class OsGcalEventRecurrenceModel extends OsModel{
  var $id,
      $until,
      $lp_event_id,
      $frequency,
      $interval,
      $count,
      $weekday,
      $created_at,
      $updated_at;

  function __construct($id = false){
    parent::__construct();
    $this->table_name = LATEPOINT_TABLE_GCAL_RECURRENCES;
    $this->nice_names = array();

    if($id){
      $this->load_by_id($id);
    }
  }

  protected function allowed_params($role = 'admin'){
    $allowed_params = array(  'id',
                              'until',
                              'lp_event_id',
                              'frequency',
                              'interval',
                              'count',
                              'weekday',
                              'created_at',
                              'updated_at');
    return $allowed_params;
  }
  
  protected function params_to_save($role = 'admin'){
    $params_to_save = array('id',
                              'until',
                              'lp_event_id',
                              'frequency',
                              'interval',
                              'count',
                              'weekday',
                              'created_at',
                              'updated_at');
    return $params_to_save;
  }

  protected function properties_to_validate(){
    $validations = array(
      'lp_event_id' => array('presence'),
      'frequency' => array('presence'),
    );
    return $validations;
  }
}
<?php

class OsGoogleCalendarEventModel extends OsModel{
  var $id,
      $summary,
      $start_date,
      $end_date,
      $start_time,
      $end_time,
      $agent_id,
      $html_link,
      $google_event_id,
      $google_calendar_id,
	    $start_datetime_utc,
	    $end_datetime_utc,
      $created_at,
      $updated_at;

  function __construct($id = false){
    parent::__construct();
    $this->table_name = LATEPOINT_TABLE_GCAL_EVENTS;
    $this->nice_names = array();

    if($id){
      $this->load_by_id($id);
    }
  }

  protected function get_nice_start_date(){
    $d = OsWpDateTime::os_createFromFormat("Y-m-d", $this->start_date);
    return $d->format("M j, Y");
  }
  
  protected function get_nice_start_time(){
    return OsTimeHelper::minutes_to_hours_and_minutes($this->start_time);
  }


  protected function allowed_params($role = 'admin'){
    $allowed_params = array(  'id',
                              'summary',
                              'start_date',
                              'end_date',
                              'start_time',
                              'end_time',
                              'agent_id',
                              'html_link',
                              'google_event_id',
                              'google_calendar_id',
                              'start_datetime_utc',
                              'end_datetime_utc',
                              'created_at',
                              'updated_at');
    return $allowed_params;
  }
  
  protected function params_to_save($role = 'admin'){
    $params_to_save = array('id',
                              'summary',
                              'start_date',
                              'end_date',
                              'start_time',
                              'end_time',
                              'agent_id',
                              'html_link',
                              'google_event_id',
                              'google_calendar_id',
                              'start_datetime_utc',
                              'end_datetime_utc',
                              'created_at',
                              'updated_at');
    return $params_to_save;
  }



  protected function properties_to_validate(){
    $validations = array(
      'google_event_id' => array('presence'),
      'agent_id' => array('presence'),
      'start_date' => array('presence'),
    );
    return $validations;
  }


  public function delete($id = false){
    if(!$id && isset($this->id)){
      $id = $this->id;
    }
    if($id && $this->db->delete( $this->table_name, array('id' => $id), array( '%d' ))){
      $this->db->delete(LATEPOINT_TABLE_GCAL_RECURRENCES, array('lp_event_id' => $id), array( '%d' ) );
      return true;
    }else{
      return false;
    }
  }


  public function update_recurrences($recurrences){
    if(!$this->id) return;
    $this->db->delete(LATEPOINT_TABLE_GCAL_RECURRENCES, array('lp_event_id' => $this->id), array( '%d' ) );
    if(!empty($recurrences)){
      // save recurrences
      foreach($recurrences as $recurrence){
        $recurrence->lp_event_id = $this->id;
        $recurrence->save();
      }
    }
  }
}
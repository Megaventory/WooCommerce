<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
/* Other code to take care of how do you find how many files are left 
   this is really not required */
function sendStatusViaSSE($task_id){
    $status = getStatus($task_id);
    $json_payload = array('filesDone' => $status.files_done,
                          'filesTotal' => $status.files_total);
    echo 'data: ' . json_encode( $json_payload ) . '\n\n';
    ob_flush();
    flush();

    // End of the process
    if( $status.done ){ die(); }

}

while( True ){
     sendStatusViaSSE( $request.$task_id );
     sleep(4);
}

?>
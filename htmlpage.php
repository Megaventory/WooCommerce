<?php
    /////// ERROR TABLE ///////
    global $wpdb;
    $error_table_name = $wpdb->prefix . "mvwc_errors";
    $errors = $wpdb->get_results("SELECT * FROM $error_table_name ORDER BY created_at DESC LIMIT 50;");

    $success_table_name = $wpdb->prefix . "success_log";
    $successes = $wpdb->get_results("SELECT * FROM $success_table_name ORDER BY created_at DESC LIMIT 50;");
    
    $error_table = '
        <h2 class="red">Error log</h2>
        <div class="userNotificationTable-wrap">
        <table id="error-log" class="wp-list-table widefat fixed striped posts">
            <tr>
                <th id="id">Error Id</th>
                <th id="id">Megaventory Id</th>
                <th id="id">WooCommerce Id</th>
                <th>Created at</th>
                <th id="type">Error type</th>
                <th>Entity name</th>
                <th id="problem">Problem</th>
                <th id="full-msg">Full message</th>
                <th id="code">Code</th>
            </tr>';
            foreach ($errors as $error) {
                $str = '<tr>';

                $str .= '<td>' . $error->id . '</td>';
                $str .= '<td>' . $error->mv_id . '</td>';
                $str .= '<td>' . $error->wc_id . '</td>';
                $str .= '<td>' . $error->created_at . '</td>';
                $str .= '<td>' . $error->type . '</td>';
                $str .= '<td>' . $error->name . '</td>';
                $str .= '<td>' . $error->problem . '</td>';
                $str .= '<td>' . $error->message . '</td>';
                $str .= '<td>' . $error->code . '</td>';

                $str .= '</tr>';
                $error_table .= $str;
            }
    $error_table .= '</table></div>';

    $success_table = '
        <h2 class="green">Import log</h2>
        <div class="userNotificationTable-wrap">
        <table id="success-log" class="wp-list-table widefat fixed striped posts">
            <tr>
            <th id="id">Import Id</th>
            <th>Created at</th>
            <th>Entity type</th>
            <th>Entity name</th>
            <th id="Transaction Status">Status</th>
            <th id="full-msg">Full message</th>
            <th id="code">Code</th>
        </tr>';
        foreach ($successes as $success) {
            $str = '<tr>';

            $str .= '<td>' . $success->id . '</td>';
            $str .= '<td>' . $success->created_at . '</td>';
            $str .= '<td>' . $success->type . '</td>';
            $str .= '<td>' . $success->name . '</td>';
            $str .= '<td>' . $success->transaction_status. '</td>';
            $str .= '<td>' . $success->message . '</td>';
            $str .= '<td>' . $success->code . '</td>';

            $str .= '</tr>';
            $success_table .= $str;
        }
    
    $success_table.='</table></div>';

    $taxes = Tax::wc_all();
    $tax_table = '
        <h2>Taxes</h2>
        <div class="tax-wrap">
        <table id="taxes" class="wp-list-table widefat fixed striped posts">
            <tr>
                <th id="id">id</th>
                <th>Megaventory Id</th>
                <th>name</th>
                <th>rate</th>
            </tr>';
            foreach ($taxes as $tax) {
                $str = '<tr>';

                $str .= '<td>' . $tax->WC_ID . '</td>';
                $str .= '<td>' . $tax->MV_ID . '</td>';
                $str .= '<td>' . $tax->name . '</td>';
                $str .= '<td>' . $tax->rate . '</td>';
                $str .= '</tr>';
                $tax_table .= $str;
            }

    $tax_table .= "</table></div>";

    global $correct_connection, $correct_currency, $correct_key;
    global $connection_value,$currency_value,$key_value,$initialize_value;
    $products_call="products";

    $initialized = (bool)get_option("mv_initialized");
    $html = '
        <div class="mv-admin">
        <h1>Megaventory</h1>
        <div class="mv-row row-main">
            <div class="mv-col">
                <h3>Status</h3>
                <div class="mv-status">
                    <ul class="mv-status">
                        <li class="mv-li-left">Connection:</li><li>'.$connection_value.'</li>
                        <li class="mv-li-left">Key: </li><li>'.$key_value.'</li>
                        <li class="mv-li-left">Currency: </li><li>'.$currency_value.'</li>
                        <li class="mv-li-left">Initialized: </li><li>'.$initialize_value.'</li>
                    </ul>
                </div>
            </div>
            <div class="mv-col">
                <h3>Setup</h3>
                <div class="mv-row">
                    <div class="mv-form">
                        <form id="options" method="post" action="'.esc_url(admin_url('admin-post.php')).'">
                            <input type="hidden" name="action" value="megaventory">
                            <div class="mv-form-body">
                                <p>
                                    <label class="MarLe30 width25per" for="api_key">Megaventory API key: </label>
                                    <input type="password" class="flLeft MarLe15 halfWidth" name="api_key" value="' . get_api_key(). ' " id="api_key"><img class="width10per flLeft MarLe15" src="https://cdn1.iconfinder.com/data/icons/eyes-set/100/eye1-01-128.png" onmouseover="mouseoverPass();" onmouseout="mouseoutPass();" />
                                    <script>
                                    function mouseoverPass(obj) {
                                        var obj = document.getElementById("api_key");
                                        obj.type = "text";
                                    }
                                    function mouseoutPass(obj) {
                                        var obj = document.getElementById("api_key");
                                        obj.type = "password";
                                    }
                                    </script>
                                
                                </p>
                                <p>
                                    <label class="width25per" for="api_host">Megaventory API host: </label>
                                    <input class="flLeft MarLe15 halfWidth" type="text" id="api_host" name="api_host" value="' . get_api_host() . '"/>
                                </p>
                                <div class="mv-form-bottom atomic_textAlignRight">
                                    <input class="updateButton" type="submit" value="update"/>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="mv-col">
                <h3>Initialization</h3>
                <div class="wrap">

                    <div id="initialize" class="margin5px page-title-action CurPointer" onclick="ajaxInitialize(0,0,5,\'initialize\')" >
                        Initialize
                    </div>
                    <div id="sync-wc-mv"  class="margin5px page-title-action CurPointer" onclick="ajaxImport(0,5,0,0,\'products\')" >
                    
                        Import Products from WC to MV

                        </div>
                    <div id="sync-clients"  class="margin5px page-title-action CurPointer" onclick="ajaxImport(0,5,0,0,\'clients\')" >
                    Import clients from WC to MV
                        </div>
                    <div id="sync-coupons" class="margin5px page-title-action CurPointer" onclick="ajaxImport(0,5,0,0,\'coupons\')" >
                    Import coupons
                    </div>
                    
                </div>
            </div>
        </div>

        <div class="mv-row row-main">
            '.$success_table.'
        </div>	

        <div class="mv-row row-main">
            '.$error_table.'
        </div>
        
        <div class="mv-row row-main">
            '.$tax_table.'
        </div>

        </div>
        <div id="loading" class="none">
            <div id="InnerLoading"></div>

            <h1>Current Sync Count: 0%</h1>
            
            <div class="InnerloadingBox">
                <span>.</span><span>.</span><span>.</span><br>
            </div>
        </div>
        <script src="/js/ajaxCallImport.js, __FILE__></script>
        <script src="/js/ajaxCallInitialize.js, __FILE__></script>
    ';
?>
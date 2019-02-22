<?php

function AsyncImport()
{	
	global $error_count,$success_count;
	$errors=0;$successes=0;

	$startingIndex = (int)$_POST["startingIndex"];
	$numberOfIndexesToProcess = (int)$_POST["numberOfIndexesToProcess"];
	$call = $_POST["call"];
	$success_count = isset($_POST["successes"])? $success_count= (int)$_POST["successes"]:null;
	$errors_count = isset($_POST["errors"])? $errors_count=(int)$_POST["errors"]:null;

	if($call=="products"){

		$wc_products = Product::wc_all();
		$numberOfProducts=count($wc_products);

		for($i = $startingIndex; $i<$numberOfIndexesToProcess+$startingIndex; $i++) {
			
			if(count($wc_products) > $i) {
				$flag=$wc_products[$i]->mv_save();
				$wc_products[$i]->sync_stock();
				$flag?$successes++:$errors++;
			}
		}

		$success_count+=$successes;
		$errors_count+=$errors;

		if ($numberOfIndexesToProcess+$startingIndex > $numberOfProducts) {
			$successMessage = "TestFinishedSuccessfully";
			if($success_count>0){
				$message="$success_count products have been imported/updated successfully in your Megaventory account.";
				log_notice("success",$message);
			}
			if($errors_count>0){
				$message="$errors_count products haven't been imported in your Megaventory account. "." Please check the Error log below for more information.";
				log_notice("error",$message);
			}
		}
		else 
			$successMessage = "continue";
		
		$dataToReturn = create_json_for_ajaxImports($startingIndex,$numberOfIndexesToProcess,$numberOfProducts,$success_count,$errors_count,$successMessage);
		echo $dataToReturn;
		die();
	}	

	if($call=="clients"){

		$wc_clients = Client::wc_all();
		$numberOfClients=count($wc_clients);
		
		for($i = $startingIndex; $i<$numberOfIndexesToProcess+$startingIndex; $i++) {
			
			if(count($wc_clients) > $i) {
				if($wc_clients[$i]!=null){
				 $wc_clients[$i]->mv_save();
				 $successes++;
				}
				else
				 $errors++;	
				} 
		}
		$success_count+=$successes;
		$errors_count+=$errors;
	
		if ($numberOfIndexesToProcess+$startingIndex > count($wc_clients)) {
			$successMessage = "TestFinishedSuccessfully"; 
			if($success_count>0){
				$message="$success_count customers have been imported/updated successfully in your Megaventory account.";
				log_notice("success",$message);
			}
			if($errors_count>0){
			$message="$errors_count users haven't been imported in your Megaventory account. "." Only customers are being imported.";
			log_notice("error",$message);
			}
		}
		else {
			$successMessage = "continue";
			
		}

		$dataToReturn = create_json_for_ajaxImports($startingIndex,$numberOfIndexesToProcess,$numberOfClients,$success_count,$errors_count,$successMessage);
		echo $dataToReturn;
		die();
	}

	if($call=="coupons"){

		$coupons = Coupon::WC_all();
		$numberOfCoupons=count($coupons);

		for($i = $startingIndex; $i<$numberOfIndexesToProcess+$startingIndex; $i++) {
			
			if(count($coupons) > $i) {
				$flag=$coupons[$i]->MV_save(); 
				$flag?$successes++:$errors++;
			}
		}

		$success_count+=$successes;
		$errors_count+=$errors;

		if ($numberOfIndexesToProcess+$startingIndex > count($coupons)) {
			$successMessage = "TestFinishedSuccessfully";
			if($success_count>0){
				$message="$success_count coupons have been imported/updated successfully in your Megaventory account.";
				log_notice("success",$message);
			}
			if($errors_count>0){
			$message="$errors_count coupons haven't imported in your Megaventory account. "." Please check the Error log below for more information.";
			log_notice("error",$message);
			}
		}
		else {
			$successMessage = "continue";
		 }

		 $dataToReturn = create_json_for_ajaxImports($startingIndex,$numberOfIndexesToProcess,$numberOfCoupons,$success_count,$errors_count,$successMessage);
		 echo $dataToReturn;
		 die();

	}

	if($call=="initialize"){

		$block=(int)$_POST["block"];

		$numberOfBlocks=6;

		if ($block==0){

			/* Create guest client in wc if does not exist yet. */
			$user_name = "WooCommerce_Guest";
			$id = username_exists($user_name);
			if (!$id) {
				$id = wp_create_user("WooCommerce_Guest", "Random Garbage", "WooCommerce@wordpress.com");
				update_user_meta($id, "first_name", "WooCommerce");
				update_user_meta($id, "last_name", "Guest");
			}

			$wc_main = Client::wc_find($id);
			$response = $wc_main->mv_save();
			update_option("woocommerce_guest", (string)$wc_main->WC_ID);
			$step=$block+1;
			$percent=(int)(($step/$numberOfBlocks)*100);
			$successMessage= "continue";
			$block++;

			$dataToReturn = create_json_for_ajaxInitialize($block,0,$percent,$successMessage);
			echo $dataToReturn;
			die();
		}	
		if ($block==1){

			$products = Product::wc_all();
			$numberOfProducts = count($products);
			for($i=$startingIndex;$i<$numberOfIndexesToProcess+$startingIndex;$i++){

				if(count($products)>$i){
					$productToInitialize = Product::mv_find_by_sku($products[$i]->SKU);
						if ($productToInitialize) {
							$productToInitialize->WC_ID=$products[$i]->WC_ID;
							$productToInitialize->sync_post_meta_with_ID();
				
					}
				}		
			}
			if ($numberOfIndexesToProcess+$startingIndex > count($products)){
				$block++;
				$step=$block;
			}
			else 
				$step=$block+1;

			$startingIndex=$numberOfIndexesToProcess+$startingIndex;
			$successMessage = "continue";

			$percent=calculatePercentOnInitialize($numberOfBlocks,$startingIndex,$numberOfProducts,$step);

			$dataToReturn = create_json_for_ajaxInitialize($block,$startingIndex,$percent,$successMessage);

			echo $dataToReturn;
			die();
			}

		if ($block==2){

			map_existing_clients_by_email();
			$step=$block+1;
			$percent=(int)(($step/$numberOfBlocks)*100);
			$successMessage= "continue";
			$block++;
			$dataToReturn = create_json_for_ajaxInitialize($block,0,$percent,$successMessage);
			echo $dataToReturn;
			die();

		}

		if ($block==3){

			initialize_taxes();
			$step=$block+1;
			$percent=(int)(($step/$numberOfBlocks)*100);
			$successMessage= "continue";
			$block++;
			$dataToReturn = create_json_for_ajaxInitialize($block,0,$percent,$successMessage);
			echo $dataToReturn;
			die();

		}
		if ($block==4){

			$products = Product::wc_all();
			$step=$block+1;
			$percent=(int)(($step/$numberOfBlocks)*100);
			$successMessage= "continue";
			$block++;
			$dataToReturn = create_json_for_ajaxInitialize($block,0,$percent,$successMessage);
			echo $dataToReturn;
			die();

		}

		if($block==5){

			update_option("mv_initialized", (string)true);

			$step=$block;
			$percent=(int)(($step/$numberOfBlocks)*100);
			$successMessage = "TestFinishedSuccessfully";
			$block++;

			$message1="Plugin successfully initialized! Now you can import products, clients and coupons in your Megaventory account.";
			$message2="Please keep in mind that this process will take place only once. After that, synchronization will happen automatically!";
			log_notice("success",$message1);
			log_notice("notice",$message2);

			$percent=$percent>100?100:$percent;
			$dataToReturn = create_json_for_ajaxInitialize($block,0,$percent,$successMessage);
			echo $dataToReturn;
			die();

		}
	}
}
function log_notice($type,$message){

	global $wpdb;
	$notices_table_name = $wpdb->prefix . "notices";
	
	$charset_collate = $wpdb->get_charset_collate();

	$query = $wpdb->insert($notices_table_name, array
		(
		"type" => $type,
		"message" => $message,
		)	
		);
		
	return $query;
}	
function create_json_for_ajaxImports($startingIndex,$numberOfIndexesToProcess,$countOfEntity,$success_count,$errors_count,$successMessage){

	$jsonData=new \stdClass();
	
	$processPercent=(int)(100*($numberOfIndexesToProcess+$startingIndex)/$countOfEntity);
	$processPercentFixed=$processPercent>100?100:$processPercent;

	$jsonData->startingIndex=$numberOfIndexesToProcess+$startingIndex;
	$jsonData->CurrentSyncCountMessage="Current Sync Count: ". $processPercentFixed."%";
	$jsonData->SuccessCount=$success_count;
	$jsonData->ErrorsCount=$errors_count;
	$jsonData->successMessage=$successMessage;


	return json_encode($jsonData);

}
function create_json_for_ajaxInitialize($block,$startingIndex=0,$percent,$successMessage){

	$jsonData=new \stdClass();

	$jsonData->block=$block;
	$jsonData->percentMessage="Current Sync Count: ". $percent . "%";
	$jsonData->successMessage=$successMessage;
	$jsonData->startingIndex=$startingIndex;

	return json_encode($jsonData);

}
function calculatePercentOnInitialize($numberOfBlocks,$startingIndex,$countOfEntity,$step){

	$localPercent=(1/$numberOfBlocks*($startingIndex/$countOfEntity));
	$percent=(int)(((($step/$numberOfBlocks)-(1/$numberOfBlocks))+$localPercent)*100);

	return $percent;
}
?>
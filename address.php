<?php

function format_address($ar) {
	$name = $ar['name'];
	$company = $ar['company'];
	$line_1 = $ar['line_1'];
	$line_2 = $ar['line_2'];
	$city = $ar['city']; 
	$county = $ar['county'];
	$postcode = $ar['postcode'];
	$country = $ar['country'];
	
	$address = '';
	if ($name != null and !ctype_space($name)) {
		$address .= $name . "\n";
	}
	if ($company != null and !ctype_space($company)) {
		$address .= $company . "\n";
	}
	if ($line_1 != null and !ctype_space($line_1)) {
		$address .= $line_1 . "\n";
	}
	if ($line_2 != null and !ctype_space($line_2)) {
		$address .= $line_2 . "\n";
	}
	if ($city != null and !ctype_space($city)) {
		$address .= $city . "\n";
	}
	if ($county != null and !ctype_space($county)) {
		$address .= $county . "\n";
	}
	if ($postcode != null and !ctype_space($postcode)) {
		$address .= $postcode . "\n";
	}
	if ($country != null and !ctype_space($country)) {
		$address .= $country . "\n";
	}
	
	return $address;
}

?>

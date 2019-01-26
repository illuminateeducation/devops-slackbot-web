<?php
function is_slack_signed($timestamp, $slack_sig, $secret, $raw_body)
{
	$sig_basestring = "v0:".$timestamp.":".$raw_body;
	$signature = "v0=".hash_hmac("sha256", $sig_basestring, $secret);

	return ($signature === $slack_sig)? true : false;
}


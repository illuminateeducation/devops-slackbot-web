<?php
// JIRA Ticket Linker
// Note: `throw new Exception("my error message");' does not work, results in a server error
//	 Instead, using `exit("my error message");' to "gracefully" quit while still showing a message
//	 in slack

// Get the file that contains our secure signature
include_once("../../includes/constants.php");		// Need constants: JIRA_TICKET_LINKER_SECRET and ATLASSIAN_PROJECT
include_once("../../includes/slack_verify.php");	// Need function: is_slack_signed(...)

$ticket_id = filter_input(INPUT_POST, "text");

// Make sure $ticket_id is reasonable
if(strlen($ticket_id) > 25)
{
	exit("Ticket ID is too long. Must be less than 25 characters");
}

// Make sure $ticket_id is in a valid format
if(!preg_match("/^[a-zA-Z0-9]*-[0-9]*$/", $ticket_id))
{
	exit("Invalid ticket ID.");
}

$headers = apache_request_headers();
$timestamp = $headers["X-Slack-Request-Timestamp"];

// Make sure it's not stale/replay attempt (60 * 3 = 3min)
if(abs(time() - $timestamp) > (60 * 3))
{
	exit("Request is stale, ignoring.");
}

$slack_signed = is_slack_signed(
	$timestamp,
	$headers["X-Slack-Signature"],
	JIRA_TICKET_LINKER_SECRET,
	file_get_contents("php://input")
);

// Make sure signature matches
if(!$slack_signed)
{
	exit("Invalid request.");
}

$linker_user_id = filter_input(INPUT_POST, "user_id");
$response_url = "https://".ATLASSIAN_PROJECT.".atlassian.net/browse/{$ticket_id}";
$response_msg = "<@{$linker_user_id}> linked a ticket: {$response_url}";

// The actual response body
$resp_arr = array(
	"response_type" => "in_channel",
	"text" => $response_msg
);

// Make the curl request
$ch = curl_init(filter_input(INPUT_POST, "response_url"));
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, "Content-type: application/json");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($resp_arr));

curl_exec($ch);
curl_close($ch);


<?php
if (!defined("IN_ESO")) exit;

/**
 * MemberProfiles plugin: allows a whole bunch of customization options 
 * for members in the form of "profile settings."
 */
class MemberProfiles extends Plugin {

var $id = "MemberProfiles";
var $name = "MemberProfiles";
var $version = "1.0";
var $description = "Allows members to further customize their profiles";
var $author = "GigaHacer, grntbg";


function init()
{
	global $config, $language;

	parent::init();

	// Language definitions.
	$this->eso->addLanguage("Profile settings", "Profile settings");
	$this->eso->addLanguage("About me", "About me");
	$this->eso->addLanguage("Signature", "Signature");
	$this->eso->addLanguage("BioDesc", "About me</br><small style='display:block'>Anything that breaks the <a href='/help/rules'>global rules</a> will be deleted</small>");
	$this->eso->addLanguage("SignatureDesc", "Signature</br><small style='display:block'>You may not use this for advertising purposes</small>");
	$this->eso->addLanguage("Show about you on your profile", "Show \"about me\" on my profile");
	$this->eso->addLanguage("Show your email on your profile", "Show my email on my profile");
	$this->eso->addLanguage("Show your signature on your posts", "Show my signature on my posts");
	$this->eso->addLanguage("hideSignatures", "Disable signatures on all posts");
	$this->eso->addLanguage("optionEverybody", "to everybody");
	$this->eso->addLanguage("optionMembers", "to logged-in members only");
	$this->eso->addLanguage("optionNobody", "to nobody at all");
	$this->eso->addLanguage("Email address", "Email address");
	// Error messages.
	$this->eso->addMessage("bioTooShort", "warning", "You must enter at least one character!");
	$this->eso->addMessage("bioTooLong", "warning", "Your \"about me\" is too long.");
	$this->eso->addMessage("signatureTooShort", "warning", "You must enter at least one character!");
	$this->eso->addMessage("signatureTooLong", "warning", "Your signature is too long.");

	// If we're on the settings view, add the extra profile settings and handle requests.
	if ($this->eso->action == "settings") {
		$this->eso->addToHead("<style type='text/css'>
			#settingsProfile label {width:32em}
			.form textarea {float:left; margin:0; width:25em}
			@media only screen and (max-width: 870px) {
				.form textarea {clear:left}
			} @media only screen and (max-width: 400px) {
				.form textarea {width:100%; width:-webkit-fill-available}
			}
		</style>");
		$this->eso->controller->addHook("init", array($this, "addProfileSettings"));
		$this->eso->controller->addHook("init", array($this, "addOtherSettings"));
	}

	// If we're on the profile view, add the bio and the bio css.
	if ($this->eso->action == "profile") {
		$this->eso->addToHead("<style type='text/css'>
			.profileBio{word-wrap:break-word; padding:2px;}
			.profile .body.aboutMe:last-child{padding-bottom:0}
			.profile .body.aboutMe p {word-wrap:break-word; text-align:justify}
			.profile .body.aboutMe h3 {margin:0 0 5px; border-bottom:1px solid #bbb}
			.profile .body.aboutMe hr {display:block; color:#bbb; height:1px; border:solid #bbb; border-width:1px 0 0}
		</style>");
		$this->eso->controller->addHook("init", array($this, "addAboutSection"));
		$this->eso->controller->addHook("statistics", array($this, "addEmail"));
	}

	// If we're on the conversation view, add the signatures to the posts.
	if ($this->eso->action == "conversation") {
		$this->eso->addCSS("plugins/ProfilesPlus/ProfileConversation.css");
		$this->eso->addToHead("<style type='text/css'>
			.p .parts > div .footer {display:none}
			.p .parts > div:last-child .footer {display:initial}
			.p .parts > div .footer > hr#divider {margin:1em -3px 5px -3px; border:solid rgba(0,0,0,0.2); border-width:1px 0 0}
			.p .parts > div .footer > p:last-child {margin-bottom:7px}
		</style>");
		$this->eso->controller->addHook("beforePostArray", array($this, "addSignature"));
	}
}

function addProfileSettings(&$settings)
{
	global $config, $language;

	$showAboutOptions = "";
	$aboutOptions = array(
		"everybody" => $language["optionEverybody"],
		"members" => $language["optionMembers"],
		"nobody" => $language["optionNobody"],
	);
	foreach ($aboutOptions as $k => $v)
		$showAboutOptions .= "<option value='$k'" . ($this->eso->user["profileShowBio"] == $k ? " selected='selected'" : "") . ">$v</option>";

	$showSignatureOptions = "";
	$signatureOptions = array(
		"everybody" => $language["optionEverybody"],
		"members" => $language["optionMembers"],
		"nobody" => $language["optionNobody"],
	);
	foreach ($signatureOptions as $k => $v)
		$showSignatureOptions .= "<option value='$k'" . ($this->eso->user["profileShowSignature"] == $k ? " selected='selected'" : "") . ">$v</option>";

	$showEmailOptions = "";
	$emailOptions = array(
		"everybody" => $language["optionEverybody"],
		"members" => $language["optionMembers"],
		"nobody" => $language["optionNobody"],
	);
	foreach ($emailOptions as $k => $v)
		$showEmailOptions .= "<option value='$k'" . ($this->eso->user["profileShowEmail"] == $k ? " selected='selected'" : "") . ">$v</option>";

	// This appears to be necessary so as to preserve line breaks.
//	$bio = stripslashes($this->eso->user["bio"]);
	$bio = stripslashes($this->eso->db->result("SELECT bio FROM {$config["tablePrefix"]}members WHERE memberId='" . $this->eso->user["memberId"] . "'"));
	$signature = stripslashes($this->eso->user["signature"]);

	$settings->addFieldset("settingsProfile", $language["Profile settings"], 0);
	$settings->addToForm("settingsProfile", array(
		"id" => "aboutMe",
		"html" => "<label>{$language["BioDesc"]}</label><textarea maxlength='1000' rows='5' id='aboutMe' name='aboutMe'>" . desanitize($this->formatForEditing($bio)) . "</textarea>",
		"databaseField" => "bio",
		"required" => false,
		"validate" => array($this, "validateAboutMe"),
	), 100);
	$settings->addToForm("settingsProfile", array(
		"id" => "showAboutMe",
		"html" => "<label>{$language["Show about you on your profile"]}</label> <select id='showAboutMe' name='showAboutMe'>$showAboutOptions</select>",
		"databaseField" => "profileShowBio",
		"required" => true,
		"validate" => array($this, "validateShowMembers"),
	), 200);
	$settings->addToForm("settingsProfile", array(
		"id" => "showEmail",
		"html" => "<label>{$language["Show your email on your profile"]}</label> <select id='showEmail' name='showEmail'>$showEmailOptions</select>",
		"databaseField" => "profileShowEmail",
		"required" => true,
		"validate" => array($this, "validateShowNobody"),
	), 300);
	$settings->addToForm("settingsProfile", array(
		"id" => "signature",
		"html" => "<label>{$language["SignatureDesc"]}</label><textarea maxlength='1000' rows='5' id='signature' name='signature'>" . desanitize($this->formatForEditing($signature)) . "</textarea>",
		"databaseField" => "signature",
		"required" => false,
		"validate" => array($this, "validateSignature"),
	), 400);
	$settings->addToForm("settingsProfile", array(
		"id" => "showSignature",
		"html" => "<label>{$language["Show your signature on your posts"]}</label> <select id='showSignature' name='showSignature'>$showSignatureOptions</select>",
		"databaseField" => "profileShowSignature",
		"required" => true,
		"validate" => array($this, "validateShowEverybody"),
	), 500);
}

function addOtherSettings(&$settings)
{
	global $language;
	if (!isset($this->eso->user["hideSignatures"])) $_SESSION["hideSignatures"] = $this->eso->user["hideSignatures"] = 0;
	$settings->addToForm("settingsOther", array(
		"id" => "hideSignatures",
		"html" => "<label for='hideSignatures' class='checkbox'>{$language["hideSignatures"]}</label> <input id='hideSignatures' type='checkbox' class='checkbox' name='hideSignatures' value='1' " . ($this->eso->user["hideSignatures"] ? "checked='checked' " : "") . "/>",
		"databaseField" => "hideSignatures",
		"checkbox" => true,
		"required" => true
	), 550);
}

// Convert a post from HTML back to formatting code.
function formatForEditing($content)
{
	return $this->eso->formatter->revert($content, array("bold", "italic", "heading", "superscript", "strikethrough", "link", "fixedBlock", "fixedInline", "specialCharacters", "whitespace", "emoticons"));
}

// Convert a post from formatting code to HTML.
function formatForDisplay($content)
{
	return $this->eso->formatter->format($content, array("bold", "italic", "heading", "superscript", "strikethrough", "link", "fixedBlock", "fixedInline", "specialCharacters", "whitespace", "emoticons"));
}

// Validate the "about me" against a maximum length of 1,000 characters and format it to be stored in the database.
function validateAboutMe(&$bio)
{
	if (strlen($bio) < 1) $this->eso->message("bioTooShort");
	elseif (strlen($bio) > 1000) $this->eso->message("bioTooLong");
//	$bio = $this->eso->db->escape($this->formatForDisplay($bio));
	// Apply string conversion (formatForDisplay) when adding the about section; not at DB level.
	// This is a workaround.  For some reason, the formatter doesn't like working on two strings at once.
	$bio = $this->eso->db->escape($bio);
}

// Validate the signature against a maximum length of 1,000 characters and format it to be stored in the database.
function validateSignature(&$signature)
{
	if (strlen($signature) < 1) $this->eso->message("signatureTooShort");
	elseif (strlen($signature) > 1000) $this->eso->message("signatureTooLong");
	$signature = $this->eso->db->escape($this->formatForDisplay($signature));
}

function validateShowEverybody(&$aboutOptions)
{
	if (!in_array($aboutOptions, array("everybody", "members", "nobody"))) $aboutOptions = "everybody";
}

function validateShowMembers(&$aboutOptions)
{
	if (!in_array($aboutOptions, array("everybody", "members", "nobody"))) $aboutOptions = "members";
}

function validateShowNobody(&$aboutOptions)
{
	if (!in_array($aboutOptions, array("everybody", "members", "nobody"))) $aboutOptions = "nobody";
}

// Add the about section to the profile page.
function addAboutSection(&$controller)
{
	global $config, $language;

	$showBio = $this->eso->db->result("SELECT profileShowBio FROM {$config["tablePrefix"]}members WHERE memberId='" . $controller->member["memberId"] . "'");

	if (($showBio == "everybody") or ($showBio == "members" and $this->eso->user)) {
		$content = stripslashes($this->eso->db->result("SELECT bio FROM {$config["tablePrefix"]}members WHERE memberId='" . $controller->member["memberId"] . "'"));
		if (!empty($content)) {
			$section = "<div class='hdr'><h3>" . $language["About me"] . "</h3></div><div class='body aboutMe'>" . $this->formatForDisplay($content) . "</div>";
			$controller->addSection($section, -1);
		}
	}
}

// Add the email address to the profile page.
function addEmail()
{
	global $config, $language;

	// Get the memberId from the URL. If none is specified, they must be viewing their own profile.
	if (isset($_GET["q2"])) {
		$memberId = $_GET["q2"];
	} else {
		$memberId = $this->eso->user["memberId"];
	}

	$showEmail = $this->eso->db->result("SELECT profileShowEmail FROM {$config["tablePrefix"]}members WHERE memberId='" . $memberId . "'");

	if (($showEmail == "everybody") or ($showEmail == "members" and $this->eso->user)) {
		$result = $this->eso->db->result("SELECT email FROM {$config["tablePrefix"]}members WHERE memberId='" . $memberId . "'");
		if (!empty($result)) {
			echo "<li><label>" . $language["Email address"] . "</label><div>" . desanitize($result) . "</div></li>";
		}
	}
}

// Add the signatures to the posts.
function addSignature(&$controller, &$post)
{
	global $config;
//	$this->conversation =& $controller->conversation;

	$showSignature = $this->eso->db->result("SELECT profileShowSignature FROM {$config["tablePrefix"]}members WHERE memberId='" . $post["memberId"] . "'");

	if (!empty($this->eso->user["hideSignatures"])) return;
	elseif (($showSignature == "everybody") or ($showSignature == "members" and $this->eso->user)) {
		$content = stripslashes($this->eso->db->result("SELECT signature FROM {$config["tablePrefix"]}members WHERE memberId='" . $post["memberId"] . "'"));
		if (!empty($content)) {
			$post["content"] .= "<div class='footer'><hr id='divider'/>" . $content . "</div>";
		}
	}
}

// Add the table to the database.
function upgrade($oldVersion)
{
	global $config;

	// BIO
	// Contents of the bio.
	if (!$this->eso->db->numRows("SHOW COLUMNS FROM {$config["tablePrefix"]}members LIKE 'bio'")) $this->eso->db->query("ALTER TABLE {$config["tablePrefix"]}members ADD COLUMN bio text default NULL");
	// Who can see the individual user's bio. default = members
	if (!$this->eso->db->numRows("SHOW COLUMNS FROM {$config["tablePrefix"]}members LIKE 'profileShowBio'")) $this->eso->db->query("ALTER TABLE {$config["tablePrefix"]}members ADD COLUMN profileShowBio enum('everybody','members','nobody') NOT NULL default 'members'");
	// EMAIL
	// Who can see the individual user's email address. default = nobody
	if (!$this->eso->db->numRows("SHOW COLUMNS FROM {$config["tablePrefix"]}members LIKE 'profileShowEmail'")) $this->eso->db->query("ALTER TABLE {$config["tablePrefix"]}members ADD COLUMN profileShowEmail enum('everybody','members','nobody') NOT NULL default 'nobody'");
	// SIGNATURE
	// Contents of the signature.
	if (!$this->eso->db->numRows("SHOW COLUMNS FROM {$config["tablePrefix"]}members LIKE 'signature'")) $this->eso->db->query("ALTER TABLE {$config["tablePrefix"]}members ADD COLUMN signature text default NULL");
	// Whether to hide signatures on all posts. default = false
	if (!$this->eso->db->numRows("SHOW COLUMNS FROM {$config["tablePrefix"]}members LIKE 'hideSignatures'")) $this->eso->db->query("ALTER TABLE {$config["tablePrefix"]}members ADD COLUMN hideSignatures tinyint(1) NOT NULL default '0'");
	// Who can see the individual user's signature. default = everybody
	if (!$this->eso->db->numRows("SHOW COLUMNS FROM {$config["tablePrefix"]}members LIKE 'profileShowSignature'")) $this->eso->db->query("ALTER TABLE {$config["tablePrefix"]}members ADD COLUMN profileShowSignature enum('everybody','members','nobody') NOT NULL default 'everybody'");
}

}

?>

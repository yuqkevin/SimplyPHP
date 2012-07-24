#!/usr/bin/php
<?php
if (!$bean_name=@$argv[1]) {
	echo <<<EOT
No bean name specified.
Usage:
	$argv[0] beanName
Notice: beanName should be in camel case if it is a sub-bean.


EOT;
	exit;
}
$class_name = ucfirst($bean_name);
$timestamp = date("Y-m-d H:i");
$user = get_current_user();
// create folder for bean
$path = strtolower(preg_replace("/([A-Z])/", "/\\1", $bean_name));
if ($path[0]==='/') $path=substr($path,1);

if (is_dir($path)) {
	echo <<<EOT
Failure!!!
	The folder for bean $bean_name has already existed.
	Please remove that folder then try again.

EOT;
	exit;
}
if (!mkdir($path, 0777, true)) {
	echo <<<EOT
Failure!!!
	Couldn't create the folder.
	Please make sure the current folder is writable for user $user and try again.

EOT;
	exit;
}
foreach (array('database','handler','view') as $folder) mkdir("$path/$folder");

// create files
file_put_contents("$path/README", "=== $class_name ===\n\t\t-- $timestamp Create by $user\n");
file_put_contents("$path/database/db.sql", "/** Database Table Definition **/\n");

$body = <<<EOT
# Table Object Definitions
#[table_hook_name]
#name   = table_name
#pkey   = field_name_of_primary_key
#prefix = prefix_of_field_name

# schema is optional, for tables which schema is not current selected schema
#schema = schema_name
# following two parameters are optional, for tree structure only
#parent = field_name_of_parent_field
#weight = field_name_of_weight_field

EOT;
file_put_contents("$path/database/db.tbl.ini", $body);
echo "# Table Object Definitions\n">"$path/database/db.tbl.ini";

$body = <<<EOT
<?php
/*** Model $class_name
 *   Create: $timestamp
 *   Author: $user
***/
class Mo$class_name extends Model
{
	protected \$dependencies = array();
}
EOT;
file_put_contents("$path/model.class.php", $body);

$body = <<<EOT
<?php
/*** Library Lib$class_name
 *   Create: $timestamp
 *   Author: $user
***/
class Lib$class_name extends Library
{
    protected \$dependencies = array();
}
EOT;
file_put_contents("$path/lib.class.php", $body);
echo "\nOK, the skeleton of bean $class_name has been created.\n";

$body = <<<EOT
; Component and action definitions and access control
[MODEL::]
; model class name in camelcase
name = "$class_name"
; description of the model
description = ""

; protection option, omitted or set to Off will be disable the model's proctection, that means the model is public accessible.
; values On/Off/"full": Off: public access; On: only listed handlers can be protected; "full":all handlers are protected
protection = "full"

[HANDLER::]
; handler definitions
; handlerName = "handler description"

; FOLLOWING SECTION IS FOR ACTIONS DEFINED IN HANDLERS
; [handlerName1]
; action1 = "description1"
; action2 = "description2"

; [handlerName2]
; action1 = "description1"
; action2 = "description2"
; ...
EOT;
file_put_contents("$path/access.ini", $body);

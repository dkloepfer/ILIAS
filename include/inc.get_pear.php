<?php
ini_set("error_reporting", E_ALL  & ~E_NOTICE);
// First use local pear
ini_set('include_path',"./Services/PEAR/lib;".ini_get('include_path'));

// look for embedded pear
if (is_dir("./pear"))
{
	ini_set("include_path", "./pear;".ini_get("include_path"));
}

?>
<?

/* ========================================================================

This script does a global find and replace regex operation on a database
column. The basic steps of the operation are as follows:

1) Retrieve the value of each row in the identified column.
2) Using the supplied selection pattern, identify one or more substrings 
   to be processed.
3) Using the supplied replacement pattern, identify a string to be replaced
   within all substrings. This pattern may contain subpatterns, whose 
   captured values may be used in constructing the replacement string.
4) Replace instances of the replacement pattern in all substrings with the
   supplied replacement string.
5) Perform a simple, string-based find/replace operation on all values.
6) Update the database table with the new value for each row.

======================================================================== */


/* === Define database, regex, search, and replace parameters. === */

/* Include a file that defines a function named db(), which returns 
   a mysql database connection object. */
require '/var/www/newtonacademy.org/na_smf_new-db.php';

// Choose a table and column to work on. 
$table_name = 'smf_messages';
$field_name = 'body';
$primary_key = 'id_msg';

/* The selection pattern identifies substrings within each row's value. 
   Each substring will be further processed with the replacement pattern 
   and the replacement string. Note that in order for your selection 
   pattern to identify multiple substrings within a single value, your 
   selection pattern's quantifiers must not be greedy. */
$selection_pattern = '/<a.*?class="mediaelement_offsetmarker".*?>/';

/*  The replacement pattern identifies the string within each substring
    that you want replaced. Use subpatterns if you need to extract values
    to use in your replacement string. */
$replacement_pattern = '/href="#(.*):(\d.*?)"/';

/*  The replacement string function returns a string that replaces your 
    replacement pattern matches. You can access subpattern values through
    the $subpattern[] array. */ 
function replacement_string ($subpatterns) {
  if ( $subpatterns[1] == 0 ) $subpatterns[1] = '0.00';
  return 'href="#" ' . 'data-mediaelement="' . $subpatterns[0] . '" data-seconds="' . $subpatterns[1] . '"';
}

// Optionally, perform an additionl global search and replace on all rows.
$final_global_search = 'mediaelement_offsetmarker';
$final_global_replace = 'timecode-link';

/* ===================================================================== */


// Get each row's value from the database and add it to an array.
$db = db();
$result = $db->query("SELECT `$primary_key`, `$field_name` FROM `$table_name`");
$values = [];
while ($row = $result->fetch_assoc()) {
	$values[] = $row;
}

// Loop through the row values.
foreach($values as &$value) {

  // Get an array of strings matching the selection pattern.
  $selection_matches = [];
  preg_match_all($selection_pattern, $value[$field_name], $selection_matches);
  $selection_matches = $selection_matches[0];
  
  // If we have matches, loop through them and perform the second replacement.
  if ( count($selection_matches) > 0 ) foreach($selection_matches as &$selection_match) {
    
    // Run the second pattern and get the captured subpatterns.
    $replacement_matches = [];
    preg_match($replacement_pattern, $selection_match, $replacement_matches);
    array_shift($replacement_matches); $subpatterns = $replacement_matches;
    
    // Get the new $selection_match and replace it in the current value.
    $selection_match_replacement = preg_replace($replacement_pattern, replacement_string($subpatterns), $selection_match);
    $value[$field_name] = str_replace($selection_match, $selection_match_replacement, $value[$field_name]);
  } unset($selection_match);
} unset($value);

// Perform the final global search/replace operation.
foreach($values as &$value) {
  $value[$field_name] = str_replace($final_global_search, $final_global_replace, $value[$field_name]);
} unset($value);

// Update the database rows with their new values.
foreach($values as $value) {
  $escaped_value = $db->real_escape_string($value[$field_name]);
  $db->query("UPDATE `$table_name` SET `$field_name`='$escaped_value' WHERE `$primary_key`=$value[$primary_key]");
}

$db->close();

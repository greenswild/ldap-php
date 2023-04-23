<?php
# edit ldap host, user,pass then call this file as ldap.php?dn where dn is in the format like ou=company,o=com
header("Content-Type: text/html; charset=UTF-8\n");
$ds=ldap_connect($ldap[host]) ;
ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
if (!@ldap_bind($ds,$ldap[admin],$ldap[password])) {
	echo 'LDAP connection failed.' ;
	exit;
}

if ($QUERY_STRING) { $dn="$QUERY_STRING";}
// should switch to search mode if no dn specified

$rdn=explode(',',$dn);
while(list($key,$val)=each($rdn)) {
	reset($rdn);
	$newdn=implode(',',$rdn);
	// do not separate dn part by newline, we may want to copy all line for other use
	$part[]="<A HREF='?$newdn'>".array_shift($rdn)."</A>";
	$menu[]="<OPTION>$newdn</OPTION>";

}
$ldn="<B>".implode(',',$part)."</B><BR>\n";
$mdn="<SELECT NAME='adn'>".implode('\n',$menu)."</SELECT>";


function my_ldap_delete($ds,$dn){
       //searching for sub entries, don't use get_entry which search recursively
		$sr=ldap_list($ds,$dn,"ObjectClass=*",array(""));
        $info = ldap_get_entries($ds, $sr);
		for($i=0;$i<$info['count'];$i++){
           //deleting recursively sub entries
           $result=my_ldap_delete($ds,$info[$i]['dn']);
           if(!$result){
               //return error if delete fails
               return(ldap_error($ds));
           }
       }
       return(ldap_delete($ds,$dn));
}


if ($drop && $en) {
	while(list($ddn,$val)=each($en)) {
		if(!my_ldap_delete($ds,$ddn)) {
			echo ldap_error($ds);
		}
	}
}

if ($create && $newattr && $newval) {
	$entry=array();
	while (list($key,$val)=each($newattr)) {
		// append value to array, to allow attribule with multiple values
		if ($newattr[$key] && $newval[$key]) { $entry[$newattr[$key]][]=$newval[$key];}
	}
	$subdn=$newattr[dn].'='.$newval[dn].','.$dn;
	if (!@ldap_add($ds,$subdn,$entry)) {
		echo ldap_error($ds);
		$new=true;
		//  display created on-the-fly by DOM wasn't bear thru POST, reprint them
		for ($i=0;$i<count($newattr)-2;$i++) {
			$rows.="<TR><TD><INPUT TYPE='text' NAME='newattr[$i]' value='".$newattr[$i]."'></TD><TD><INPUT TYPE='text' NAME='newval[$i]' value='".$newval[$i]."'></TD></TR>\n";
		}
	}
}

if ($new) {
?>

<SCRIPT LANGUAGE="JavaScript">
function add(){
var NUM = document.getElementsByTagName("TR").length-2;
            mycurrent_row=document.createElement("TR");
				mycurrent_cell=document.createElement("TD");
                currenttext=document.createElement('input');
				currenttext.setAttribute('type','text');
 				currenttext.setAttribute('name','newattr['+NUM+']');
 				currenttext.setAttribute('size','15');
               mycurrent_cell.appendChild(currenttext);
                mycurrent_row.appendChild(mycurrent_cell);

				mycurrent_cell=document.createElement("TD");
                currenttext=document.createElement('input');
				currenttext.setAttribute('type','text');
 				currenttext.setAttribute('name','newval['+NUM+']');
				mycurrent_cell.appendChild(currenttext);
                mycurrent_row.appendChild(mycurrent_cell);

			document.getElementById('add').appendChild(mycurrent_row);
}
</SCRIPT>
<FORM METHOD=POST ACTION="">
<TABLE>
<tbody id='add'>
<TR>
	<TD>dn: <INPUT TYPE="text" NAME="newattr[dn]" size='10' value="<?=$newattr[dn]?>">=</TD>
	<TD><INPUT TYPE="text" NAME="newval[dn]" value="<?=$newval[dn]?>">,<?=$ldn?></TD>
</TR>
<TR>
	<TD><INPUT TYPE="hidden" NAME="newattr[objectClass]" value='objectClass'>objectClass</TD>
	<TD><INPUT TYPE="text" NAME="newval[objectClass]" value="<?=$newval[objectClass]?>"></TD>
</TR>
	<?=$rows?>
</tbody>
</TABLE>
<INPUT TYPE="submit" name='create' value='create'>
</FORM>
<INPUT TYPE='button' name='add' value='add attribute' onClick="javascript:add()">

<?php
	echo "</body></html>";
	exit;
}

if ($save) {

	while(list($key1,$val1)=each($ent)) {
		// some attribute like facsimilieTelephoneNumber has no equality rule in schema, hence single value
		// ldap_mod_del/ldap_mod_replace cannot handle it, removing regardless of it's value: $attrs["AttributeName"]=array(); 
		$members=count($ent[$key1]); // single or multi?
		while(list($key2,$val2)=each($val1)) {
			// don't interpret zero as no value, see "PHP type comparison tables" for more details
			if ($val2 || $val2=='0') {
				// also remove trailing white space or new line
				$new[$key1][$key2]=utf8_encode(trim($val2)); 
			} else {
				# require $new appear here to avoid replacing non-sequence index
				if ($members==1) { 
					$del[$key1]=$new[$key1][$key2]=array() ;
				} else { 
					$del[$key1][]=$new[$key1][$key2]=utf8_encode($old[$key1][$key2]);
				}
			}
		}
	}
	if(!@ldap_mod_replace($ds,$dn,$new)) {
		echo ldap_error($ds);
	}
	if ($del) {
		if(!@ldap_mod_del($ds,$dn,$del)) {
			echo ldap_error($ds);
		}
	}

}

if ($attribute && ($value||$value=='0')) {
	$entry[$attribute]=utf8_encode($value);
	if(!ldap_mod_add($ds,$dn,$entry)) {
		echo ldap_error($ds);
	}
}

?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="HandheldFriendly" content="true" />
<meta name="viewport" content="width=device-width, height=device-height, user-scalable=no" />
</head>
<body>
<FORM METHOD=POST ACTION=''>
<?
echo "<input name='q' type='text'>  $mdn <input name='search' type='submit' value='search'><BR>\n";

if ($q) {
	$sr=ldap_search($ds, $adn, $q,array());
	$info = ldap_get_entries($ds, $sr);
	if ($count=$info[count]) {
		for ($i=0;$i<$count;$i++) {
			$sdn=$info[$i][dn];
			echo "<A HREF='?$sdn'>$sdn</A><BR>";
		}
	}
	echo "</body></html>";
	exit;
}

echo $ldn  ;

$sr=ldap_list($ds, $dn, '(objectclass=*)' , array());
$info = ldap_get_entries($ds, $sr);
if ($count=$info[count]) {
	for ($i=0;$i<$count;$i++) {
		$newdn=$info[$i][dn];
		$r=explode(',',$newdn);
		echo "<INPUT TYPE='checkbox' NAME='en[$newdn]'> <A HREF='edit.php?$newdn'>".$r[0]."</A><BR>\n";
	}
	echo "<INPUT TYPE='submit' name='drop' value='drop'>\n";

}
echo "<INPUT TYPE='submit' name='new' value='new dn'> <p>\n";

echo "<INPUT TYPE='hidden' name='dn' value='$dn'><TABLE>\n<tbody id='add'>\n";
$sr=ldap_read($ds, $dn, '(objectclass=*)' , array());
$info = ldap_get_entries($ds, $sr);
for ($i=0;$i<$info[0][count];$i++) {
	$name=$info[0][$i];
	$entry='';
	for ($j=0;$j<$info[0][$name][count];$j++) {
		$entry=ent.'['.$name.']['.$j.']' ;
		$oldval=old.'['.$name.']['.$j.']' ;
		$k=$j+1;
		$val=htmlspecialchars(utf8_decode($info[0][$name][$j]),ENT_QUOTES);
		$size=strlen($val);
		if (ereg("\n",$val,$out)) {
			$inum=substr_count($val, "\n")+1;
			$input="<TEXTAREA NAME='$entry' ROWS='$inum' COLS='$size'>$val</TEXTAREA>";
		} else {
			$input="<INPUT TYPE='text' NAME='$entry' VALUE='$val' size='$size'>";
		}
		echo "<TR><TD>$name</TD><TD> $input <INPUT TYPE='hidden' NAME='$oldval' VALUE='$val'></TD></TR>\n";
	}
}
ldap_close($ds);

?>

</tbody></TABLE>
<INPUT TYPE='submit' name='save' value='save'>
<P>
<table border="0">
  <tr> 
    <td>attribute</td>
    <td><input name="attribute" type="text"></td>
  </tr>
  <tr> 
    <td>value</td>
    <td><input name="value" type="text"></td>
  </tr>
  <tr>
    <td><input name="add" type="submit" value="add"></td>
    <td>&nbsp;</td>
  </tr>
</table>

<P>
</FORM>
</body>
</html>

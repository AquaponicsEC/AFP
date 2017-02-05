<!DOCTYPE>
<html>
<?php 
include ("../rf_InitSession.php");
include ("../rf_func.php");
include ("../utils/ColorBox.php");
?>
	
	
<body>
<div id="main">   
    <h1>My Vectors</h1>
	
	<div style="min-width:400px;margin-left:20px;margin-top:-10px;border-bottom:#C5C5C5 solid 1px;"></div>
<br/>

<?php 

$template = $_SESSION['usrpath'].'template.xml';
if (!$xml = simplexml_load_file($template)) die; 

echo "<table border='0'><tr><th>Object</th><th>Display</th><th>Color</th><th>Width</th></tr>";

for($i=0;$xml->vector[$i];$i++)
{ 

	echo "<tr><td>";
	echo $xml->vector[$i]->attributes()->name;
	echo "</td><td>";
	
	$onoff= $xml->vector[$i]->attributes()->hidden;
	if ($onoff==false){$value='checked';} else {$value='';}
	
	echo "<label style='display: block; padding: 5px 30px 5px 0px; background-color: white; width: 10px; height: 20px'>";
	echo "<input style='margin-left:18px;' type='checkbox' id='onoff$i' $value></label></td>";

	$color= $xml->vector[$i]->attributes()->color;
//	echo "<input class='color' id='color$i' size='8' value='$color' /></td><td>";
	echo "<td><div id='color$i' style='border:#000 solid 1px; background: $color; width: 30px; height:15px' onclick='GetColor(\"color$i\");'></div></td>";

	$width= $xml->vector[$i]->attributes()->width;
	echo "<td><input style='border: 0px;' id='width$i' size='2' value=$width></td>";
	
	
	$tpnb++;
}
echo "</table>";
	

 
?>	
		<br/>
	 <input  id="close" class="rsButton" type="button" value="Update" onclick="update()"/>
<br/><br/>
	<div style="min-width:400px;margin-left:20px;border-bottom:#C5C5C5 solid 1px;"></div>

</body>


<script>

function update()
{
	var id;
	var col;
	
	var url = "../svrUpdateTemplate.php?fct=vecs";
	for(var i=0; document.getElementById("onoff"+i.toString()); i++)
	{
		url += "&onoff"+i.toString()+"="+document.getElementById("onoff"+i.toString()).checked.toString();
		col = colorToHex(document.getElementById("color"+i.toString()).style.background);
		url += "&color"+i.toString()+"="+col;
		url += "&width"+i.toString()+"="+document.getElementById("width"+i.toString()).value.toString();
		alert(url);
	}
};

function colorToHex(color) {
    if (color.substr(0, 1) === '#') {
        return color;
    }
    var digits = /(.*?)rgb\((\d+), (\d+), (\d+)\)/.exec(color);
    
    var red = parseInt(digits[2]);
    var green = parseInt(digits[3]);
    var blue = parseInt(digits[4]);
    
    var rgb = blue | (green << 8) | (red << 16);
    return digits[1] + '#' + rgb.toString(16);
};
</script>
</html>


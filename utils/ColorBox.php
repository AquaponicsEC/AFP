<!--
ColorBox.php Color Picker
include after rfmain.css
-->



<div id="ColorBox" class="modalPage"> 
    <style type="text/css" scoped>

#ColorBox table 
{ 
    border: 0px; 
    margin-left:0px; 
    margin-bottom:0px;
    background: #fbfbfb;
    font: normal 12px/1.8em Arial, Helvetica, sans-serif;
    
}

#ColorBox td { height: 25px; width:25px; border: 2px solid #FFF; padding: 0px;}
</style>
    <div class="modalBackground"></div> 
    <div class="modalContainer" style="width: 250px;" >
        <div class="mCont_top" onclick="SetColor()">Select Color</div><br/>
        <div class="colp" style="width: 100%; text-align: center">
 <?php
$cols = array(	'#FFFFFF','#FFCCCC','#FFCC99','#FFFF99','#FFFFCC','#99FF99','#99FFFF','#CCFFFF','#CCCCFF','#FFCCFF',
				'#CCCCCC','#FF6666','#FF9966','#FFFF66','#FFFF33','#66FF99','#33FFFF','#66FFFF','#9999FF','#FF99FF',
				'#C0C0C0','#FF0000','#FE9900','#FFCC66','#FFFF00','#33FF33','#66CCCC','#33CCFF','#6666CC','#CC66CC',
				'#999999','#CC0000','#FF6600','#FFCC33','#FFCC00','#33CC00','#00CCCC','#3366FF','#6633FF','#CC33CC',
				'#666666','#990000','#CC6600','#CC9933','#999900','#009900','#339999','#3333FF','#6600CC','#993399',
				'#333333','#660000','#993300','#996633','#666600','#006600','#336666','#000099','#333399','#663366',
				'#000000','#330000','#663300','#663333','#333300','#003300','#003333','#000066','#330099','#330033');

echo "<table  style='border:3px solid gray'>";

for($i=0;$i<7;$i++)
{
	echo "<tr>";
        for($j=0;$j<10;$j++){ echo "<td onclick='SetColor(\"".$cols[$i*10+$j]."\")' style=\"background:".$cols[$i*10+$j].";\"></td>";}              
	echo "</tr>";
}
echo "</table>";

?>
</div>	</div>	</div>


<script>

var ColorId;

function GetColor(ids)
{
    window.onscroll = function () { document.getElementById('ColorBox').style.top = 0;}//document.body.scrollTop; };
    document.getElementById('ColorBox').style.display = "block";
    document.getElementById('ColorBox').style.top = 0;//document.body.scrollTop;
	ColorId = document.getElementById(ids);
	return;
}
	
function SetColor(cols)
{
	document.getElementById('ColorBox').style.display = "none";
	if(cols) ColorId.style.background=cols;
}
function colorToHex(color) {
    if (color.substr(0, 1) === '#') {
        return color;
    }
    var digits = /(.*?)rgb\((\d+), (\d+), (\d+)\)/.exec(color);
    
	var red = parseInt(digits[2]).toString(16);
    var green = parseInt(digits[3]).toString(16);
    var blue = parseInt(digits[4]).toString(16);
    if(red.length==1) red='0'+red;
    if(green.length==1) green='0'+green;
    if(blue.length==1) blue='0'+blue;
	return red+green+blue;
//    var rgb = blue | (green << 8) | (red << 16);
//    return digits[1] + '#' + rgb.toString(16);
};

</script>

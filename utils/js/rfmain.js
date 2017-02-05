var isMobile = {
    Android: function() {return navigator.userAgent.match(/Android/i);},
    BlackBerry: function() {return navigator.userAgent.match(/BlackBerry/i);},
    iOS: function() {return navigator.userAgent.match(/iPhone|iPad|iPod/i);},
    Opera: function() {return navigator.userAgent.match(/Opera Mini/i);},
    Windows: function() {return navigator.userAgent.match(/IEMobile/i);},
    any: function() {return (isMobile.Android() || isMobile.BlackBerry() || isMobile.iOS() || isMobile.Opera() || isMobile.Windows());}
};


function HideSidebar(winw){
    document.getElementById("sidebar").style.display = 'none';
    document.getElementById("imgopen").style.display = 'block';
    document.getElementById("imgclose").style.display = 'none';
    document.getElementById("main").style.marginLeft = '27px';

    var x = winw - 27;
    document.getElementById("main").style.width = x +"px"; 
}

function ShowSidebar(winw)
{
    document.getElementById("sidebar").style.display = 'block';
    document.getElementById("imgopen").style.display = 'none';
    document.getElementById("imgclose").style.display = 'block';
    document.getElementById("main").style.marginLeft = '252px';

    var x = winw - 252;
    document.getElementById("main").style.width = x +"px";   
}

function revealModal(divID){
//    window.onscroll = function () { document.getElementById(divID).style.top = document.body.scrollTop; };
    document.getElementById(divID).style.display = "block";
    document.getElementById(divID).style.top = document.body.scrollTop;
}

function hideModal(divID){ document.getElementById(divID).style.display = "none"; }

function parentRevealModal(divID){
    window.parent.document.getElementById(divID).style.display = "block";
    window.parent.document.getElementById(divID).style.top = document.body.scrollTop;
}

function parentHideModal(divID){ window.parent.document.getElementById(divID).style.display = "none"; }

function hideIframe(id)
{
    if (self != top) {
        window.parent.document.getElementById(id).style.display = "none";
    }
}

function showid(divID){ document.getElementById(divID).style.display = "block";}
function hideid(divID){ document.getElementById(divID).style.display = "none";}
function setAtt(divID,attribute,value){ document.getElementById(divID).setAttribute(attribute,value);}
function disabled(divID){ document.getElementById(divID).disabled = true;}
function able(divID){ document.getElementById(divID).disabled = false;}
function innerHTML(divID,text){ document.getElementById(divID).innerHTML = text;}

function parentHideIContent(divId){ 
    window.parent.document.getElementById(divId).style.display = "block";; 
    window.parent.document.getElementById('iContent').innerHTML = ''; 
}

function parentHidejformIContent(){ 
    window.parent.document.getElementById('jform').style.display = "block";; 
    window.parent.document.getElementById('iContent').innerHTML = ''; 
}

function scrolldiv(element) {
    var el = document.getElementById(element);

    el.ontouchstart = function(event){
        startY = event.touches[0].pageY;
        startX = event.touches[0].pageX;
    };

    el.ontouchmove = function(event){
        var touches = event.touches[0];
        event.preventDefault();
        
        var touchMovedX = startX - touches.pageX;
        startX = touches.pageX; // reset startX for the next call
        el.scrollLeft = el.scrollLeft + touchMovedX;

        var touchMovedY = startY - touches.pageY;
        startY = touches.pageY; // reset startY for the next call
        el.scrollTop = el.scrollTop + touchMovedY;
    }
}
  
// WMAS ***********************************************************************************

function WMAS2DEC(xin, yin)
{
    var lon, lat;

    if(xin>20037508.3427892) xin-=(20037508.3427892*2);
    if(xin<-20037508.3427892) xin+=(20037508.3427892*2);
    
    yin = Math.min(yin,20037508.3427892);
    yin = Math.max(yin,-20037508.3427892);

    num4 = xin / 6378137.0 * 57.295779513082323;
    num5 = Math.floor((num4 + 180.0) / 360.0);

    lon = num4 - (num5 * 360.0);
    lat = 57.295779513082323 * (1.5707963267948966 - (2.0 * Math.atan(Math.exp((-1.0 * yin) / 6378137.0))));
    return { lon: lon, lat: lat };
}

function DEC2WMAS(lon, lat)
{
    var xout, yout;
    if ((Math.abs(lon) > 180 || Math.abs(lat) > 85.05113)) return {xout: 0, yout: 0};
    num = lon * 0.017453292519943295;
    xout = 6378137.0 * num;
    a = lat * 0.017453292519943295;
    yout = 3189068.5 * Math.log((1.0 + Math.sin(a)) / (1.0 - Math.sin(a)));
    return {xout: xout, yout: yout};
}

function GetGroundResolution(lat, lvl)
{
	var res = (Math.cos(lat * Math.PI/180) * 2 * Math.PI * 6378137.) / (1024. * Math.pow(2.,lvl));
        return res;
}
function LVL2RES(lvl) { return (19567.87924 / (1<<(lvl-1))); }

function ArcDistance(lon1,lat1,lon2,lat2)
{
	var a1,b1,a2,b2,pi,dist;

	pi = Math.PI;

	a1 = lat1*pi/180;
	b1 = lon1*pi/180;
	a2 = lat2*pi/180;
	b2 = lon2*pi/180;

	dist = Math.acos(Math.cos(a1)*Math.cos(b1)*Math.cos(a2)*Math.cos(b2) + Math.cos(a1)*Math.sin(b1)*Math.cos(a2)*Math.sin(b2)+Math.sin(a1)*Math.sin(a2)) * 6370.;
	return dist;
}
function GetTrueCourse(lon1,lat1,lon2,lat2)
{
	var pi = 3.141592654;
	lon1 = -lon1 * pi/180;
	lat1 = lat1 * pi/180;
	lon2 = -lon2 * pi/180;
	lat2 = lat2 * pi/180;

	var ang = ((Math.atan2(Math.sin(lon1-lon2)*Math.cos(lat2),Math.cos(lat1)*Math.sin(lat2)-Math.sin(lat1)*Math.cos(lat2)*Math.cos(lon1-lon2))) % (2*pi))* 180./pi;
	if(ang<0) ang=360+ang;
    return ang;
}

function bearingDegrees  (lon1,lat1,lon2,lat2)
{
    var x = Math.sin(lon2-lon1) * Math.cos(lat2);
    var y = Math.cos(lat1)*Math.sin(lat2) - Math.sin(lat1)*Math.cos(lat2)*Math.cos(lon2-lon1);
    return ((Math.atan2(x,y) * 180/Math.PI) + 360 ) % 360;
}

function getAzimuth  (lon1,lat1,lon2,lat2)
{
    return ((Math.atan2(lon2-lon1,lat2-lat1) * 180/Math.PI) + 360 ) % 360;
}

function jformHandler(url, key, value, waitBox){
    
    url += '&key=' + key;
    url += '&value=' + value;

    var XHR = new XMLHttpRequest();
    XHR.onreadystatechange = function () {
        if (XHR.readyState === 4 && XHR.status === 200) {
            
            if(!waitBox){
                parentHideModal('wait_Box');
            }
            
            var response = JSON.parse(XHR.responseText);
            if(response && waitBox){parentHideModal('wait_Box');}
            
            if(response.fieldHTML){
                for(var i in response.fieldHTML){
                    if(window.parent.document.getElementById(i)){
                        window.parent.document.getElementById(i).innerHTML = response.fieldHTML[i];
                    }
                }
            }
            
            if(response.inputValue){

                for(var i in response.inputValue){
                    if(window.parent.document.getElementById(i)){
                        window.parent.document.getElementById(i).value = response.inputValue[i];
                    }
                }
            }
            
            window.parent.document.getElementById('showErrors').innerHTML = '';

            if (response.breResponse) {
                var jerror = window.parent.document.getElementsByClassName("jerror");
                for (var n = 0; n <= jerror.length; n++) {
                    if (jerror[n]) {
                        jerror[n].innerHTML = '';
                        jerror[n].style.display = "none";
                    }
                }
                var auto = '';
                for (var key in response.breResponse) {
                    auto = (auto === '') ? key : auto;
                    //Check if the error to show is a hidden field and show it at the bottom of the page
                    var fieldName = key.replace('[error]', '').replace('app[', '').replace('][', '.').replace('][', '.').replace('][', '.').replace(']', '');
                    var row = window.parent.document.getElementById('row_' + fieldName);
                    var hidden = '';
                    if (row) {
                        hidden = row.style.display;
                    }
                    if (window.parent.document.getElementById(key) && hidden == '') {
                        window.parent.document.getElementById(key).style.display = 'block';
                        window.parent.document.getElementById(key).innerHTML = window.parent.document.getElementById(key).innerHTML + response.breResponse[key];
                    } else {
                        window.parent.document.getElementById('showErrors').innerHTML = window.parent.document.getElementById('showErrors').innerHTML + response.breResponse[key];
                    }
                }
                smoothScroll(auto);
            }
            
            if (response.errorMsg) {
                window.parent.document.getElementById('showErrors').innerHTML = window.parent.document.getElementById('showErrors').innerHTML + response.errorMsg;
            }
            
            if(response.modal) {
                window.parent.document.getElementById('custom_Box').innerHTML = response.modal;
                parentRevealModal(response.modalId);
            }
        }
    }

    XHR.open("POST", url, true);
    XHR.send();
}

function currentYPosition() {
    // Firefox, Chrome, Opera, Safari
    if (self.pageYOffset)
        return self.pageYOffset;
    // Internet Explorer 6 - standards mode
    if (document.documentElement && document.documentElement.scrollTop)
        return document.documentElement.scrollTop;
    // Internet Explorer 6, 7 and 8
    if (document.body.scrollTop)
        return document.body.scrollTop;
    return 0;
}

function elmYPosition(eID) {
    var elm = document.getElementById(eID);
    if (elm !== null) {
        var y = elm.offsetTop;
        var node = elm;
        while (node.offsetParent && node.offsetParent !== document.body) {
            node = node.offsetParent;
            y += node.offsetTop;
        }
        return y;
    }
}

function smoothScroll(eID) {
    var startY = currentYPosition();
    var stopY = elmYPosition(eID) - 100;
    var distance = stopY > startY ? stopY - startY : startY - stopY;
    if (distance < 100) {
        scrollTo(0, stopY);
        return;
    }
    var speed = Math.round(distance / 100);
    if (speed >= 20)
        speed = 20;
    var step = Math.round(distance / 25);
    var leapY = stopY > startY ? startY + step : startY - step;
    var timer = 0;
    if (stopY > startY) {
        for (var i = startY; i < stopY; i += step) {
            setTimeout("window.scrollTo(0, " + leapY + ")", timer * speed);
            leapY += step;
            if (leapY > stopY)
                leapY = stopY;
            timer++;
        }
        return;
    }
    for (var i = startY; i > stopY; i -= step) {
        setTimeout("window.scrollTo(0, " + leapY + ")", timer * speed);
        leapY -= step;
        if (leapY < stopY)
            leapY = stopY;
        timer++;
    }
    return false;
}

function w2dbm(pwr) {
    var dbm = 10 * Math.log10(1000 * pwr);
    return dbm;
}

function dbm2w(dbm) {
    var pwr = Math.pow(10,(dbm / 10)) / 1000;
    return pwr;
}

function convertPower2dbm(pwr, unit) {
	var rtn = 0;
    if (unit == 'dBm') {
        rtn = pwr;
    } else if (unit == 'W') {
        rtn = w2dbm(pwr);
    } else if (unit == 'kW') {
        rtn = w2dbm(1000 * pwr);
    } else if (unit == 'mW') {
        rtn = w2dbm(pwr / 1000);
    } else if (unit == 'dBW') {
        rtn = pwr*1 + 30;
    } else if (unit == 'dBK') {
        rtn = pwr*1 + 60;
    }
    return rtn;
}

function convertPowerDbm2unit(pwr, unit) {
    var rtn = 0;
    if (unit == 'dBm') {
        rtn = pwr;
    } else if (unit == 'W') {
        rtn = dbm2w(pwr);
    } else if (unit == 'kW') {
        rtn = dbm2w(pwr)/1000;
    } else if (unit == 'mW') {
        rtn = dbm2w(pwr) * 1000;
    } else if (unit == 'dBW') {
        rtn = pwr*1 - 30;
    } else if (unit == 'dBK') {
        rtn = pwr*1 - 60;
    }
    return rtn;
}

function convertPowerUnits(pwr, unitOrigin, unitDest){
	pwrDbm = convertPower2dbm(pwr, unitOrigin);
	var rtn = convertPowerDbm2unit(pwrDbm, unitDest)
    return rtn;
}

function convertGainUnits(gain, unitOrigin, unitDest) {
    var rtn = 0;
    if (unitOrigin == 'dBi' && unitDest == 'dBd') {
        rtn = gain*1 - 2.14;
    } else if (unitOrigin == 'dBd' && unitDest == 'dBi') {
        rtn = gain*1 + 2.14;
    } else if (unitOrigin == 'dBd' && unitDest == 'dBd') {
        rtn = gain*1;
    } else if (unitOrigin == 'dBi' && unitDest == 'dBi') {
        rtn = gain*1;
    }
    return rtn;
}

function convertFreqUnits(freq, unitOrigin, unitDest) {
    var rtn = 0;
    if(unitOrigin == 'Hz'){
        switch (unitDest){
            case 'Hz':
                rtn = freq*1;
                break;
            case 'kHz':
                rtn = freq/1000;
                break;
            case 'MHz':
                rtn = freq/1000000;
                break;
            case 'GHz':
                rtn = freq/1000000000;
                break;
        }
    }
    if(unitOrigin == 'kHz'){
        switch (unitDest){
            case 'Hz':
                rtn = freq*1000;
                break;
            case 'kHz':
                rtn = freq*1;
                break;
            case 'MHz':
                rtn = freq/1000;
                break;
            case 'GHz':
                rtn = freq/1000000;
                break;
        }
    }
    if(unitOrigin == 'MHz'){
        switch (unitDest){
            case 'Hz':
                rtn = freq*1000000;
                break;
            case 'kHz':
                rtn = freq*1000;
                break;
            case 'MHz':
                rtn = freq*1;
                break;
            case 'GHz':
                rtn = freq/1000;
                break;
        }
    }
    if(unitOrigin == 'GHz'){
        switch (unitDest){
            case 'Hz':
                rtn = freq*1000000000;
                break;
            case 'kHz':
                rtn = freq*1000000;
                break;
            case 'MHz':
                rtn = freq*1000;
                break;
            case 'GHz':
                rtn = freq*1;
                break;
        }
    }
    
    return rtn;
}

function unitConvertion(value, unitOrigin, unitDest){
    var powerArray = ['mW','W','kW','dBm','dBW','dBK'];
    var gainArray = ['dBi','dBd'];
    var freqArray = ['Hz','kHz','MHz','GHz'];
    var lengthArray = ['cm','m','Km','in','ft'];
    var rtn = 0;
    
    if(powerArray.indexOf(unitOrigin) != -1 && powerArray.indexOf(unitDest) != -1){
	rtn = convertPowerUnits(value, unitOrigin, unitDest);
    }
    
    if(gainArray.indexOf(unitOrigin) != -1 && gainArray.indexOf(unitDest) != -1){
	rtn = convertGainUnits(value, unitOrigin, unitDest);
    }
    
    if(freqArray.indexOf(unitOrigin) != -1 && freqArray.indexOf(unitDest) != -1){
	rtn = convertFreqUnits(value, unitOrigin, unitDest);
    }
    
    return rtn;
}
function forEach(list, callback) {
    var length = list.length;
    for (var n = 0; n < length; n++) {
        callback.call(list[n]);
    }
}
// Common functions
function showTab(id) {
    var tab = document.getElementsByClassName('tab');
    var tabContent = document.getElementsByClassName('tab-panel');

    for (var i = 0; i < tab.length; i++) {
        tabContent[i].style.display = 'none';
        tabContent[i].setAttribute("class", "tab-panel");
        tab[i].setAttribute("class", "tab");
        if (tabContent[i].getAttribute('id') === id) {
            tabContent[i].style.display = 'block';
            tab[i].setAttribute("class", "tab active");
        }
    }
    document.getElementById(id).style.display = 'block';
    document.getElementById(id).setAttribute("class", "tab-panel active");

    var gMapsCount = gMapsArray.length;
    for (var i = 0; i < gMapsCount; i++) {
        var map = gMapsArray[i].map;
        var center = map.getCenter();
        google.maps.event.trigger(map, "resize");
        map.setCenter(center);
    }
}

function getStatusIcon(status) {
    switch (status) {
        case 'pass':
            return '<span class="fa fa-check" style="color: green; padding-left:5px; padding-right:5px;"></span> ';
            break;

        case 'fail':
            return '<span class="fa fa-times" style="color: #B40404; padding-left:5px; padding-right:5px;"></span>  ';
            break;

        case 'warning':
            return '<span class="fa fa-exclamation-triangle" style="color: #FFBF00; padding-left:5px; padding-right:5px;"></span> ';
            break;
    }
}

// Google Maps functions
var gMapsArray = [];

function initialize(idMap, lat, lon) {
    geocoder = new google.maps.Geocoder();
    var latlng = new google.maps.LatLng(lat.value, lon.value);
    var mapOptions = {
        zoom: 8,
        center: latlng
    };
    gMap = new google.maps.Map(document.getElementById(idMap), mapOptions);
    gMarker = new google.maps.Marker({
        map: gMap,
        draggable: true,
        position: latlng
    });
    return {map: gMap, marker: gMarker};
}

/**
 * Resize a map's div by changing the map's container classess
 * @param {string} idMap
 * @returns {undefined}
 */
function resizeMap(idMap, maxLabel, minLabel) {
    var srcMap = idMap.substring(0, (idMap.length - 4));

    if (document.getElementById(idMap + '_sizeBtn').getAttribute("data-size") === 'min') {
        // maximize the map
        document.getElementById(idMap + '_sizeBtn').innerHTML = minLabel;
        document.getElementById(idMap + '_sizeBtn').dataset.size = 'max';
        document.getElementById(idMap + '_l').className = '';
        document.getElementById(idMap + '_c').className = '';
        document.getElementById(idMap).style.height = '350px';
    } else {
        // minimize the map
        document.getElementById(idMap + '_sizeBtn').innerHTML = maxLabel;
        document.getElementById(idMap + '_sizeBtn').dataset.size = 'min';
        document.getElementById(idMap + '_l').className = 'col-4';
        document.getElementById(idMap + '_c').className = 'col-6';
        document.getElementById(idMap).style.height = '200px';
    }

    // redraw maps
    var gMapsCount = gMapsArray.length;
    for (var i = 0; i < gMapsCount; i++) {
        var map = gMapsArray[i].map;
        var marker = gMapsArray[i].marker;
        var center = marker.getPosition();
        google.maps.event.trigger(map, "resize");
        map.setCenter(center);
    }
}

function DEC2DMS(dec, isLat) {
    var num = Math.abs(dec);
    var D = Math.floor(num);
    var M = Math.floor((num - D) * 60);
    var S = Math.round(((num - D - (M / 60)) * 3600) * 10) / 10;

    if (isLat)
        O = (dec < 0) ? 'S' : 'N';
    else
        O = (dec < 0) ? 'W' : 'E';

    var DMS = {'d': D, 'm': M, 's': S, 'o': O};
    return DMS;
}

function DMS2DEC(type, i) {
    var D = parseInt(document.getElementsByClassName(type)[0].value);
    var M = parseInt(document.getElementsByClassName(type)[1].value);
    var S = parseFloat(document.getElementsByClassName(type)[2].value);

    var dec = Math.round((D + M / 60 + S / 3600) * 100000) / 100000;
    if (document.getElementsByClassName(type)[4].checked)
        var dec = dec * (-1);

    return dec;
}

function updateLocation(src, pMarker, pMap) {
    pLat = DMS2DEC(src + '_1_class');
    pLon = DMS2DEC(src + '_0_class');
    if (pLat && pLon) {
        var latlng = new google.maps.LatLng(pLat, pLon);
        pMarker.setPosition(latlng);
        pMap.panTo(latlng);
    }
}

function updateLocationFromMap(idMapCanvas, pMarker, f_lat, f_log, src_name) {
    var plat = pMarker.getPosition().lat().toFixed(5);
    var plon = pMarker.getPosition().lng().toFixed(5);

    var aDMS = DEC2DMS(plat, 1);
    var oDMS = DEC2DMS(plon, 0);

    var latPos = document.querySelectorAll('.' + src_name + '_class.' + src_name + '_1_class');
    var logPos = document.querySelectorAll('.' + src_name + '_class.' + src_name + '_0_class');

    latPos[0].value = aDMS['d'];
    latPos[1].value = aDMS['m'];
    latPos[2].value = aDMS['s'];

    logPos[0].value = oDMS['d'];
    logPos[1].value = oDMS['m'];
    logPos[2].value = oDMS['s'];

    f_lat.value = plat;
    f_log.value = plon;

    (plat > 0) ? latPos[3].checked = true : latPos[4].checked = true;
    (plon > 0) ? logPos[3].checked = true : logPos[4].checked = true;

}


// Antenna Pattern functions
function changeAntPattern(id) {
    var antType = document.getElementById('f_' + id).value;

    var fieldStatus = null;
    var grpDisplay = null;

    if (antType === 'omni') {
        // omni antenna: hide azimuth block and disable fields
        fieldStatus = true;
        grpDisplay = 'none';
    } else {
        // directional antenna: show azimuth block and enable fields
        fieldStatus = false;
        grpDisplay = 'block';
    }

    document.getElementById(id + '_grp').style.display = grpDisplay;
    var azimuthFields = document.getElementsByClassName(id);
    for (var i = 0; i < azimuthFields.length; i++) {
        azimuthFields[i].disabled = fieldStatus;
    }
}

function getFile(id) {
    document.getElementById(id + "_name").value = document.getElementById(id).value;
    return false;
}
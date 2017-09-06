$(document).ready(function() {

    function info_content(mark) {
        sessionStorage.latitudTicket = mark.position.lat();
        sessionStorage.longitudTicket = mark.position.lng();
        return ;
    }
    var initialPoint = new google.maps.LatLng(-34.067878, -60.1078219);
    var initialZoom = 14;
    var pointZoom = 16;
    var map = new google.maps.Map(document.getElementById("g-map-canvas"), {
        zoom: initialZoom,
        center: initialPoint,
        mapTypeId: google.maps.MapTypeId.ROADMAP,
        mapTypeControlOptions: {
            mapTypeIds: new Array(google.maps.MapTypeId.ROADMAP, google.maps.MapTypeId.HYBRID, google.maps.MapTypeId.SATELLITE)
        },
        styles: [{"featureType":"all","elementType":"geometry.fill","stylers":[{"hue":"#00bfff"}]},
            {"featureType":"road.highway","elementType":"geometry","stylers":[{"color":"#b9b5b5"}]},{"featureType":"water","elementType":"geometry.fill","stylers":[{"color":"#0072bc"}]}],
        streetViewControl: true
    });
    var infowindow = new google.maps.InfoWindow();

    var mark = new google.maps.Marker({
        map: map,
        position: initialPoint,
        draggable: false
    });
    infowindow.setContent(info_content(mark));
//    infowindow.open(map, mark);
    google.maps.event.addListener(mark, "dragstart", function() {
        infowindow.close();
    });
    google.maps.event.addListener(mark, "dragend", function() {
        infowindow.setContent(info_content(mark));
//        infowindow.open(map, mark);
    });
    var geocoder = new google.maps.Geocoder();
    $("#geo-form").submit(function() {
//    $("#direccion").change(function() {
        var from = jQuery.trim($("#geo_directions").val());
        geocoder.geocode({
            address: from,
            language: "es",
            region: "ES"
        }, function(results, status) {
            if (status != google.maps.GeocoderStatus.OK) {
                alert("No se pudo encontrar la dirección solicitada. Por favor, compruebe que los datos introducidos son correctos");
            } else {
                var num_results = results.length;
                if (num_results > 1) {
                    var dir_html = "<div id=\"g-sel-dir\"><p><strong>La dirección dada no es correcta. Por favor, elija una de las siguientes::</strong></p><ul>";
                    for (var i = 0; i < num_results; i++) {
                        dir_html += "<li><a href=\"#\">" + results[i].formatted_address + "</a></li>";
                    }
                    $("#g-directions").html(dir_html + "</ul></div>");
                    $("#g-sel-dir a").click(function() {
                        var geocoder2 = new google.maps.Geocoder();
                        geocoder2.geocode({
                            address: $(this).text(),
                            language: "es",
                            region: "ES"
                        }, function(results, status) {
                            mark.setPosition(results[0].geometry.location);
                            infowindow.setContent(info_content(mark));
                            map.setCenter(mark.position);
                            map.setZoom(pointZoom);
                            $("#g-directions").html("");
                            $("#geo_directions").attr("value", "");
        infowindow.close();
                     });
                        return false;
                    });
                    return false;
                } else {
                    mark.setPosition(results[0].geometry.location);
                    infowindow.setContent(info_content(mark));
                    map.setCenter(mark.position);
                    map.setZoom(pointZoom);
                    $("#g-directions").html("");
                    $("#geo_directions").attr("value", "");
                   infowindow.close();
                    sessionStorage.latitudTicket = mark.position.lat();
                    sessionStorage.longitudTicket = mark.position.lng();
                    parent.document.getElementById('formLatitud').value=mark.position.lat();
                    parent.document.getElementById('formLongitud').value=mark.position.lng();
//                    alert('latitud '+mark.position.lat());
//                    alert('longitud '+mark.position.lng());
     }
            }
        });
        return false;
    });

});

$(document).ready(function() {

//alert('mierda '+JSON.parse(sessionStorage.getItem('prioridadesItems')));

						var latitudes = JSON.parse(sessionStorage.getItem('latitudesItems'));
						var longitudes = JSON.parse(sessionStorage.getItem('longitudesItems'));
						var prioridades = JSON.parse(sessionStorage.getItem('prioridadesItems'));
						var direcciones = JSON.parse(sessionStorage.getItem('direccionesItems'));
						var temas = JSON.parse(sessionStorage.getItem('temasItems'));
						var mensajes = JSON.parse(sessionStorage.getItem('mensajesItems'));
						var categorias = JSON.parse(sessionStorage.getItem('categoriasItems'));
						var areas = JSON.parse(sessionStorage.getItem('areasItems'));
						alert("puta "+' '+prioridades+' '+latitudes[1]+' '+latitudes[2]);

	function info_content(mark) {
		return "<div id=\"infowindow\"><p class=\"infowindow-title\"><strong>Coordenadas</strong>:</p><p><strong>Latitud</strong>: " + mark.position.lat().toFixed(6) + " (" + deg_to_dms(mark.position.lat(), true) + ")</p><p><strong>Longitud</strong>: " + mark.position.lng().toFixed(6) + " (" + deg_to_dms(mark.position.lng(), false) + ")</p></div>";
		}

	var map = new google.maps.Map(document.getElementById('g-map-canvas'), {
		zoom: 14,
		center: new google.maps.LatLng(-34.067878, -60.1078219),
		mapTypeId: google.maps.MapTypeId.ROADMAP,
		mapTypeControlOptions: {
		mapTypeIds: new Array(google.maps.MapTypeId.ROADMAP, google.maps.MapTypeId.HYBRID, google.maps.MapTypeId.SATELLITE)
		},
		styles: [{"featureType":"all","elementType":"geometry.fill","stylers":[{"hue":"#00bfff"}]},
			{"featureType":"road.highway","elementType":"geometry","stylers":[{"color":"#b9b5b5"}]},{"featureType":"water","elementType":"geometry.fill","stylers":[{"color":"#0072bc"}]}],
		streetViewControl: true
		});
/*    var infowindow = new google.maps.InfoWindow();

    var mark = new google.maps.Marker({
        map: map,
        position: initialPoint,
        draggable: false
    });*/
//alert('ieie '+sessionStorage.marcadores[1][0]);
		var infowindow = new google.maps.InfoWindow();
		var marker, i;
		for (i = 0; i < latitudes.length; i++) {  
			colorIcono='img/mapiconos/'+prioridades[i]+'.png';
//			alert('poronga '+i+' '+colorIcono);
			marker = new google.maps.Marker({
			position: new google.maps.LatLng(latitudes[i], longitudes[i]),
			map: map,
			icon:colorIcono
			});
		google.maps.event.addListener(marker, 'click', (function(marker, i) {
			return function() {
				infowindow.setContent("<b>Area a Cargo:</b><br />"+areas[i]+"<br /><b>Dirección del reclamo:</b><br />"+direcciones[i]+"<br /><b>Asunto:</b><br />"+temas[i]+"<br /><b>Descripción:</b><br />"+mensajes[i]);
				infowindow.open(map, marker);
				}
			})(marker, i));
		}


});


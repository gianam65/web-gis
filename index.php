<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <title>WEBGIS tìm kiếm các bảo tàng, di tích lịch sử ở Việt Nam</title>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <link rel="stylesheet" href="https://cdn.rawgit.com/openlayers/openlayers.github.io/master/en/v5.3.0/css/ol.css" type="text/css">
        <link rel="stylesheet" href="https://openlayers.org/en/v4.6.5/css/ol.css" type="text/css" />
        <link rel="stylesheet" href="./css/style.css">
        <script src="https://cdn.rawgit.com/openlayers/openlayers.github.io/master/en/v5.3.0/build/ol.js"></script>
    </head>
    <body onload="initialize_map();">
        <div class="wrapper">
            <div class="search-section">
                <span class="search-title">Tìm kiếm</span>
                <input 
                    type="text" 
                    class="search-inp"
                    id="search-location"
                    placeholder="Nhập từ khóa để tìm kiếm"
                >
                <div class="search-result" id="search-result"></div>
            </div>
            <div id="map"></div>
        </div>
        <div class="ol-popup" id="popup">
            <div id="popup-content"></div>
        </div>
        <?php include 'CMR_pgsqlAPI.php' ?>
        <script src="https://cdn.jsdelivr.net/npm/jquery@3.2.1/dist/jquery.min.js"></script>
        <script>
            var container = document.getElementById("popup");
            var content = document.getElementById("popup-content");
            var overlay = new ol.Overlay({
                element: container,
                autoPan: true,
                autoPanAnimation: {
                    duration: 250,
                },
            });
            var styles = {
                'MultiPolygon': new ol.style.Style({
                    fill: new ol.style.Fill({
                        color: '#e74c3c'
                    }),
                    stroke: new ol.style.Stroke({
                        color: 'black', 
                        width: 1,
                    })
                })
            };
            var styleFunction = function (feature) {
                return styles[feature.getGeometry().getType()];
            };
            var vectorLayer = new ol.layer.Vector({
                //source: vectorSource,
                style: styleFunction
            });
            const searchBox = document.getElementById("search-location")
            searchBox.addEventListener("change", (e) => {
                const searchValue = e.target.value
                $.ajax({
                    type: "POST",
                    url: "CMR_pgsqlAPI.php",
                    data: { inSearchMode: true, searchValue: searchValue},
                    success : function (result, status, erro) {
                        const dataToRender = ["Từ khóa tìm kiếm không hợp lệ"].includes(result) ? result : JSON.parse(result || "[]" ); 
                        renderSearchResult(dataToRender);
                    },
                    error: function (req, status, error) {
                        console.log('error :>> ', error);
                    }
                });
            })

            function renderSearchResult(data) {
                let htmlElement = '';
                const searchResultDiv = document.getElementById('search-result')
                if(["Từ khóa tìm kiếm không hợp lệ"].includes(data)) {
                    htmlElement = '<div class="please-enter-search-value"><span>Vui lòng nhập từ khóa tìm kiếm</span></div>';  
                    searchResultDiv.innerHTML  = htmlElement;
                    return;
                }
                if(data.length == 0) {
                   htmlElement = '<div class="no-data"><span>Không tìm thấy dữ liệu</span></div>';     
                } else {
                    for(let i = 0; i < data.length; i++) {
                        htmlElement += `
                            <div class="search-result-item" onclick='moveToLocation(${data[i].geo}, "${data[i].name}")'>${data[i].name}</div>
                        `;
                    }
                }
                
                searchResultDiv.innerHTML  = htmlElement;
            }

            function calcAVGCoordinates(listOfCoordinates) {
                if(!listOfCoordinates || !Array.isArray(listOfCoordinates)) return
                let sumLonCoor = 0;
                let sumLatCoor = 0;
                let lengthOfCoordinates = listOfCoordinates.length
                for(let i = 0; i < lengthOfCoordinates; i++) {
                    sumLonCoor += listOfCoordinates[i][0];
                    sumLatCoor += listOfCoordinates[i][1];
                }
                
                return [sumLonCoor / lengthOfCoordinates, sumLatCoor / lengthOfCoordinates]
            }

            function moveToLocation(listPoint, locationName) {
                highLightObj(JSON.stringify(listPoint))
                
                const avgCoordinates = calcAVGCoordinates(listPoint.coordinates.flat(2))
                map.getView().animate({
                    center: ol.proj.fromLonLat(avgCoordinates),
                    duration: 2500,
                    zoom: 16,
                });

                setTimeout(() => {
                    $("#popup-content").html(`<div class="location-tooltip">${locationName}</div>`);
                    overlay.setPosition(ol.proj.fromLonLat(avgCoordinates));
                }, 2500);
            }

            function createJsonObj(result) {     
                var geojsonObject = '{'
                        + '"type": "FeatureCollection",'
                        + '"crs": {'
                            + '"type": "name",'
                            + '"properties": {'
                                + '"name": "EPSG:4326"'
                            + '}'
                        + '},'
                        + '"features": [{'
                            + '"type": "Feature",'
                            + '"geometry": ' + result
                        + '}]'
                    + '}';
                return geojsonObject;
            }

            function drawGeoJsonObj(paObjJson) {
                var vectorSource = new ol.source.Vector({
                    features: (new ol.format.GeoJSON()).readFeatures(paObjJson, {
                        dataProjection: 'EPSG:4326',
                        featureProjection: 'EPSG:3857'
                    })
                });
                var vectorLayer = new ol.layer.Vector({
                    source: vectorSource
                });
                map.addLayer(vectorLayer);
            }

            function highLightGeoJsonObj(paObjJson) {
                var vectorSource = new ol.source.Vector({
                    features: (new ol.format.GeoJSON()).readFeatures(paObjJson, {
                        dataProjection: 'EPSG:4326',
                        featureProjection: 'EPSG:3857'
                    })
                });
                vectorLayer.setSource(vectorSource);
                /*
                var vectorLayer = new ol.layer.Vector({
                    source: vectorSource
                });
                map.addLayer(vectorLayer);
                */
            }
            function highLightObj(result) {
                var strObjJson = createJsonObj(result);
                var objJson = JSON.parse(strObjJson);
                // drawGeoJsonObj(objJson);
                highLightGeoJsonObj(objJson);
            }
            var format = 'image/png';
            var map;
            var mapLat = 15.917; // Tọa độ của Việt Nam
            var mapLng = 107.331;
            var mapDefaultZoom = 6;
            function initialize_map() {
                layerBG = new ol.layer.Tile({
                    source: new ol.source.OSM({})
                });
                var layerCMR_adm1 = new ol.layer.Image({
                    source: new ol.source.ImageWMS({
                        ratio: 1,
                        url: 'http://localhost:8080/geoserver/example/wms?',
                        params: {
                            'FORMAT': format,
                            'VERSION': '1.1.1',
                            STYLES: '',
                            LAYERS: 'gadm41_vnm_1',
                        }
                    })
                });
                var viewMap = new ol.View({
                    center: ol.proj.fromLonLat([mapLng, mapLat]),
                    zoom: mapDefaultZoom,
                    // projection: projection
                });
                map = new ol.Map({
                    target: "map",
                    // layers: [layerBG, layerCMR_adm1],
                    layers: [layerBG],
                    view: viewMap,
                    overlays: [overlay],
                });
                // map.getView().fit(bounds, map.getSize());
                
                map.addLayer(vectorLayer);

                map.on('singleclick', function (evt) {
                    return
                    // var lonlat = ol.proj.transform(evt.coordinate, 'EPSG:3857', 'EPSG:4326');
                    // var lon = lonlat[0];
                    // var lat = lonlat[1];
                    // var myPoint = 'POINT(' + lon + ' ' + lat + ')';
                    // $.ajax({
                    //     type: "POST",
                    //     url: "CMR_pgsqlAPI.php",
                    //     data: {
                    //         functionname: 'getGeoCMRToAjax', 
                    //         paPoint: myPoint
                    //     },
                    //     success : function (result, status, erro) {
                    //         highLightObj(result);
                    //     },
                    //     error: function (req, status, error) {
                    //         alert(req + " " + status + " " + error);
                    //     }
                    // });
                });
            };
        </script>
    </body>
</html>
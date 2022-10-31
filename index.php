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
                <div class="calc-distance" id="distance-box">
                    <span class="search-title">Tìm khoảng cách giữa 2 địa điểm</span>
                    <div class="select-location-box">
                        <span class="location"> Địa điểm bắt đầu:</span>
                        <select id="select-location-1"></select>
                    </div>
                    <div class="select-location-box">
                        <span class="location">Địa điểm kết thúc:</span>
                        <select id="select-location-2"></select>
                    </div>

                    <button id="calc-btn" onclick={calcDistance()}>Tính khoảng cách</button>
                    <div id="calc-result"></div>
                </div>
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
                        renderSelectLocation(dataToRender);
                    },
                    error: function (req, status, error) {
                        console.log('error :>> ', error);
                    }
                });
            })

            function renderSelectLocation(data) {
                let htmlElement = '';
                const selectLocationDiv1 = document.getElementById('select-location-1')
                const selectLocationDiv2 = document.getElementById('select-location-2')
                if(Array.isArray(data) && data.length > 2) {
                    for (let i = 0; i < data.length; i++) {
                        htmlElement += `
                            <option value=${data[i].geo}>${data[i].name}</option>
                        `;
                    }
                    selectLocationDiv1.innerHTML = htmlElement;
                    selectLocationDiv2.innerHTML = htmlElement;
                }
            }

            function calcDistance() {
                const startedValue = JSON.parse(document.getElementById('select-location-1').value)
                const endValue = JSON.parse(document.getElementById('select-location-2').value)
                const startedValueToLonlat = calcAVGCoordinates(startedValue.coordinates.flat(2))
                const endValueToLonlat = calcAVGCoordinates(endValue.coordinates.flat(2))
                const startLonlat = ol.proj.transform(startedValueToLonlat, 'EPSG:3857', 'EPSG:4326')
                const endLonlat = ol.proj.transform(endValueToLonlat, 'EPSG:3857', 'EPSG:4326')

                $.ajax({
                    type: "POST",
                    url: "CMR_pgsqlAPI.php",
                    data: { inCalcMode: true, startedPoint: `POINT(${startLonlat[0]} ${startLonlat[1]})`, endPoint: `POINT(${endLonlat[0]} ${endLonlat[1]})`},
                    success : function (result, status, erro) {
                        const distanceResult = JSON.parse(result)[0].distance;
                        const getDistanceToRender = convertToTrulyDistance(distanceResult)
                        const resultDOM = document.getElementById("calc-result")
                        resultDOM.innerHTML = 'Khoảng cách là: ' + getDistanceToRender + 'm';
                    },
                    error: function (req, status, error) {
                        console.log('error :>> ', error);
                    }
                });
            }

            function convertToTrulyDistance(distance) {
                const splitDistanceToGetNNumber = distance.split("e-00")
                if(splitDistanceToGetNNumber.length <= 1) return distance;
                const nNumber = +splitDistanceToGetNNumber[1]
                const originalNumber = splitDistanceToGetNNumber[0].split(".")
                return `${originalNumber[0]}${originalNumber[1].slice(0, 10 - nNumber)}`;
            }

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
                    document.getElementById("distance-box").style.display = 'block';
                    for(let i = 0; i < data.length; i++) {
                        htmlElement += `
                            <div class="search-result-item" onclick='moveToLocation(${data[i].geo}, "${data[i].name}")'>${data[i].name}</div>
                        `;
                    }
                }
                
                searchResultDiv.innerHTML = htmlElement;
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
            }
            function highLightObj(result) {
                var strObjJson = createJsonObj(result);
                var objJson = JSON.parse(strObjJson);
                drawGeoJsonObj(objJson);
                highLightGeoJsonObj(objJson);
            }
            var format = 'image/png';
            var map;
            var mapLat = 15.917; // Tọa độ của Việt Nam
            var mapLng = 107.331;
            var mapDefaultZoom = 6;
            var bounds = [102.107955932617,8.30629730224609,109.505798339844,23.4677505493164];
            function initialize_map() {
                layerBG = new ol.layer.Tile({
                    source: new ol.source.OSM({})
                });
                var layerGADM_VNM_1 = new ol.layer.Image({
                    source: new ol.source.ImageWMS({
                        ratio: 1,
                        url: 'http://localhost:8080/geoserver/example/wms?',
                        params: {
                            'FORMAT': format,
                            'VERSION': '1.1.1',
                            STYLES: '',
                            LAYERS: 'example:border_vn_map',
                        }
                    })
                });
                var viewMap = new ol.View({
                    center: ol.proj.fromLonLat([mapLng, mapLat]),
                    zoom: mapDefaultZoom,
                });
                map = new ol.Map({
                    target: "map",
                    layers: [layerBG, layerGADM_VNM_1],
                    view: viewMap,
                    overlays: [overlay],
                });
                // map.getView().fit(bounds, map.getSize());
                
                map.addLayer(vectorLayer);

                map.on('singleclick', function (evt) {
                    // return
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
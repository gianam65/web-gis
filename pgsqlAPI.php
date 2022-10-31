<?php
    // require_once 'config.php';
    if(isset($_POST['functionname']))
    {
        $paPDO = initDB();
        $paSRID = '4326';
        $paPoint = $_POST['paPoint'];
        $functionname = $_POST['functionname'];

        $aResult = getGeoCMRToAjax($paPDO, $paSRID, $paPoint);
        
        echo $aResult;
    
        closeDB($paPDO);
    }

    if(isset($_POST['inSearchMode'])) {
        $paPDO = initDB();
        $searchValue = $_POST['searchValue'];

        if(!$searchValue) {
            echo "Từ khóa tìm kiếm không hợp lệ";
        } else {
            $mySQLStr = "SELECT name, ST_AsGeoJson(geom) AS geo FROM \"gis_osm_pois_a_free_1\" WHERE name ILIKE '%$searchValue%' and fclass = 'museum' LIMIT 100";
            $result = query($paPDO, $mySQLStr);
    
            if ($result != null) {
                echo json_encode($result);
            } else {
                return "[]";
            }
        }
    }

    if(isset($_POST['inCalcMode'])) {
        $paPDO = initDB();
        $startedValue = $_POST['startedPoint'];
        $endValue = $_POST['endPoint'];
        $mySQLStr = "SELECT ST_Distance('$startedValue'::geometry, '$endValue'::geometry) as distance";
        
        $result = query($paPDO, $mySQLStr);

        if ($result != null) {
            echo json_encode($result);
        } else {
            return null;
        }
    }

    function initDB()
    {
        // Kết nối CSDL
        $paPDO = new PDO('pgsql:host=localhost;dbname=TestCSDL;port=5432', 'postgres', '123456');

        return $paPDO;
    }
    function query($paPDO, $paSQLStr)
    {
        try
        {
            // Khai báo exception
            $paPDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Sử đụng Prepare 
            $stmt = $paPDO->prepare($paSQLStr);
            // Thực thi câu truy vấn
            $stmt->execute();
            
            // Khai báo fetch kiểu mảng kết hợp
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            
            // Lấy danh sách kết quả
            $paResult = $stmt->fetchAll();   
            return $paResult;                 
        }
        catch(PDOException $e) {
            echo "Thất bại, Lỗi: " . $e->getMessage();
            return null;
        }       
    }
    function closeDB($paPDO)
    {
        // Ngắt kết nối
        $paPDO = null;
    }
    
    function getResult($paPDO,$paSRID,$paPoint)
    {
        $paPoint = str_replace(',', ' ', $paPoint);
        $mySQLStr = "SELECT ST_AsGeoJson(geom) as geo from \"gadm41_vnm_1\" where ST_Within('SRID=".$paSRID.";".$paPoint."'::geometry,geom)";
        $result = query($paPDO, $mySQLStr);
        
        if ($result != null)
        {
            // Lặp kết quả
            foreach ($result as $item){
                return $item['geo'];
            }
        }
        else
            return "null";
    }
    function getGeoCMRToAjax($paPDO,$paSRID,$paPoint)
    {
        $paPoint = str_replace(',', ' ', $paPoint);
        
        $mySQLStr = "SELECT ST_AsGeoJson(geom) as geo from \"gadm41_vnm_1\" where ST_Within('SRID=".$paSRID.";".$paPoint."'::geometry,geom)";

        $result = query($paPDO, $mySQLStr);
        
        if ($result != null)
        {
            // Lặp kết quả
            foreach ($result as $item) {
                return $item['geo'];
            }
        }
        else
            return "null";
    }
?>
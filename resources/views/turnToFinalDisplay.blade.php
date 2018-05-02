<!DOCTYPE html>
<html>
<head>
     <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no">
    <title>Test</title>
       <script type="text/javascript" src="{{ asset('CesiumOld/Apps/Sandcastle/Sandcastle-header.js') }}"></script>
       <script type="text/javascript" src="{{ asset('CesiumOld/ThirdParty/requirejs-2.1.9/require.js') }}"></script>

        <script type="text/javascript">
        require.config({
            baseUrl: "{{ asset('CesiumOld/Source') }}",
            waitSeconds: 60
        });
    </script>

</head>
<body class="sandcastle-loading" data-sandcastle-bucket="bucket-requirejs.html">
    <style>
        @import url("{{ asset('CesiumOld/Apps/Sandcastle/templates/bucket.css') }}");
    </style>
    <div id="cesiumContainer" class="fullSize"></div>


    <script id="cesium_sandcastle_script">

    <?php
        echo "var obj = $data;";
    ?>

        function startup(Cesium)
        {
            'use strict';
            //Sandcastle_Begin
            Cesium.BingMapsApi.defaultKey = 'AoUP29Z-v0eqHOJaE4BaVhYJ1XuRZX04Oeiiw8if5KliJq7BbbJw9t0IrPe-Uix1';
            if (obj.is3D == true)
            {
              var viewer = new Cesium.Viewer('cesiumContainer',{sceneMode : Cesium.SceneMode.SCENE3D});
            }
            else
            {
              console.log("ITS 2D!");
              var viewer = new Cesium.Viewer('cesiumContainer',{sceneMode : Cesium.SceneMode.SCENE2D});
            }

            var gfLocation = new Cesium.Cartesian3.fromDegrees(-97.181238, 47.957674, 2631.0827);

            var homeCameraView = {
                destination: gfLocation,
                orientation: {
                    heading: 0.0,
                    pitch: -Cesium.Math.PI_OVER_TWO,
                    roll: 0.0
                }
            };

    //         viewer.scene.camera.setView(homeCameraView);
    //         viewer.homeButton.viewModel.command.beforeExecute.addEventListener(function (e) {
    // e.cancel = true;
    // viewer.scene.camera.flyTo(homeCameraView);


    console.log(obj);


    // console.log(flights);
    obj.posData.forEach(function(element)
    {
        var points = element;
        console.log(points);
        if (obj.is3D==true)
        {
          var flightPath = viewer.entities.add({
              polyline: {
              positions: Cesium.Cartesian3.fromDegreesArrayHeights(points),
              // positions: Cesium.Cartesian3.fromDegreesArrayHeights([]), //Includes heights for the lines as well
              width: 2,
              material : Cesium.Color.GREEN
          }});
        }
        else
        {
            var flightPath = viewer.entities.add({
                polyline: {
                positions: Cesium.Cartesian3.fromDegreesArray(points),
                // positions: Cesium.Cartesian3.fromDegreesArrayHeights([]), //Includes heights for the lines as well
                width: 2,
                material : Cesium.Color.BLUE
            }});
        }

    });

    var redLine = viewer.entities.add({
    name : 'Red line on the surface',
    polyline : {
        positions : Cesium.Cartesian3.fromDegreesArray(obj.eclData),
        width : 2,
        material : Cesium.Color.RED
    }
  });






viewer.zoomTo(viewer.entities);


// Set up clock and timeline.
viewer.clock.shouldAnimate = true; // default
            //Sandcastle_End
            Sandcastle.finishedLoading();
        }
        if (typeof Cesium !== "undefined") {
            startup(Cesium);
        } else if (typeof require === "function") {
            require(["Cesium"], startup);
        }
    </script>


</body>
</html>

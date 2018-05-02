<?php

use NGAFID\Airports as AP;

$airports = AP::select('*')->get();

?>

@extends ('NGAFID-master')


@section ('content')




    <div class="container-fluid">
        <h1>Turn To Final</h1>

        Set options and view flights.

        <form action="turnToFinal/runQuery">
            <input type="date" name="start">
            <input type="date" name="end">
            <select name = "airport">
            <?php
                foreach ($airports as $airport) {
                    echo "<option value = $airport->id>$airport->AirportCode $airport->Runway</option>\n";
                }
            ?>
            </select>
            <input type="radio" name="dim" value="2d" checked>2D
            <input type="radio" name="dim" value="3d" checked>3D
            <button type="submit" name = "submit" value="idString">View flights</button>
        </form>


    </div>





@endsection

<?php
/*
*Austin Holtz
*/

namespace NGAFID\Http\Controllers;

use NGAFID\Http\Requests;
use NGAFID\Http\Controllers\Controller;
use DB;
use Illuminate\Http\Request;

use NGAFID\StabilizedApproach as SA;
use NGAFID\Main as Main;
use NGAFID\FlightID as FID;
use NGAFID\airports as Airport;

class TurnToFinalController extends Controller {

	public function start()
	{
		return view('turnToFinal');
	}


	/**
	*Takes info from turnToFinal view, finds the flight the user was looking for and finds the lats and longs from the final approach.
	*@param request=getRequest from turnToFinal.blade.php
	*@return a view that will process the latitudes and longitudes from the flight into a cesium display
	*/
	public function runQuery(Request $request)
	{
		//initialize the output
		$output = new FlightDataCollection($request->dim);
		if ($request->dim=="3d")
		{
			$output->setIs3D(true);
		}
		else {
			$output->setIs3D(false);
		}

		//get the string of IDs from the request and convert it into an array
		$startDate = $request->start;
		$endDate = $request->end;
		$flights = FID::where('date','>=',$startDate)
						->where('date','<=',$endDate)
						->orderBy('id', 'asc')
						->get();
		//Creating default airport selector to run queries
		$airport = $request->input('airport'); //Gets the airportID of the one chosen by user in turnToFinal webpage

		//Run Queries in order to get extended center line of selected airport
		$airportQuery = Airport::where('id', '=', $airport)->get(); //Returns all columns of users selected airportId
		$previous = Airport::where('id', '<', $airport)->max('id'); //Returns the previous rows values from users current selected airportId
		$next = Airport::where('id', '>', $airport)->min('id'); //Returns the next rows values from users current selected airportId



		//For some reason airportQuery creates two objects when ran. The original and an exact copy of it. You need to specify first
		//print($airportQuery->first()->extendedcenterlineLong);
		//echo "<br>";
		//print($airportQuery->first()->extendedcenterlineLat);

		$ecl = array();

		//foreach($ecl as $ex) {
		//	print($ex);
		//	echo "<br>";
		//}

		//Appends airportId to the end of the data variable which eventually holds all lat,long,height,airportId and passed into turnToFinal.blade.php
		//Since extended center line (ECL) is in pairs, need to look at next sql row if airportId is even and look back if it is odd
		//TODO: Update variables so even and odds are accounted for and then append to flightStr
		if($airport % 2 == 0) { //Even result
			$ecl[] = $airportQuery->first()->extendedcenterlineLong; //Initial long
			$ecl[] = $airportQuery->first()->extendedcenterlineLat;	//Initial Lat
			$temp = Airport::where('id','=', $previous)->get();
			$ecl[] = $temp->first()->extendedcenterlineLong; //Initial long
			$ecl[] = $temp->first()->extendedcenterlineLat;	//Initial Lat
		} else if($airport % 2 == 1) { //odd result
			$ecl[] = $airportQuery->first()->extendedcenterlineLong;
			$ecl[] = $airportQuery->first()->extendedcenterlineLat;
			$temp = Airport::where('id','=', $next)->get();
			$ecl[] = $temp->first()->extendedcenterlineLong; //Initial long
			$ecl[] = $temp->first()->extendedcenterlineLat;	//Initial Lat
		}
		$queryEclData = array();
		foreach($ecl as $ex) {
			array_push($queryEclData,$ex);
		}

		$output->setEclData($queryEclData);


		//for each flight ID in the array, add the spacial data from the final approach to the output
		$colPosData = array();
		foreach ($flights as $flight)
		{
			$flightID=$flight->id;
			//find the start time of the flight
			$startTime = FID::where('id',$flightID)->get()->toArray();
			//retrieve the time of the final approach
			$startTime = array_pop($startTime)['time'];


			//convert time to seconds from 00:00
			$startTimeInSeconds = $this->timeToSeconds($startTime);

			//find the time of the turn-to-final, convert it to seconds
			$finalInfo = SA::where('flight',$flightID)->get()->toArray();
			$finalInfo = array_pop($finalInfo);
			$timeOfFinal = $finalInfo['timeOfFinal'];

			//some tables are missing this data for a given flight. This skips those flights.
			if (!isset($timeOfFinal)) continue;
			if ($finalInfo['airport_id']!=$request->airport) continue;

			$tofInSeconds = $this->timeToSeconds($timeOfFinal);

			//find the time (in milliseconds) that we need to start pulling data from the database
			//ttf begin time = (total flight time - flight start time) * 1000
			$finalBeginTime = ($tofInSeconds-$startTimeInSeconds)*1000;

			//pull the time on final from the database. This tells us how many rows to pull
			$timeOnFinal = $finalInfo['timeOnFinal'];

			//pull lat and long data from main table. Limit to the value in timeOnFinal
			$data = Main::where('flight',$flightID)
								->where('time','>=',$finalBeginTime)
								->limit($timeOnFinal)
								->orderBy('time', 'asc')
								->get();

			//create a string for the flight, add the longitudes and latitudes
			$flightPosData = array();
			foreach ($data as $datum) {
				if ($request->dim=="3d")
					array_push($flightPosData,$datum->longitude,$datum->latitude,$datum->msl_altitude);
				else
					array_push($flightPosData,$datum->longitude,$datum->latitude);

			}

			// Calculate whether flight pattern exceeds 100ft distance from ECL
			//     once approximately parallel (within 0.1 rad of ECL slope).
			// TODO: Add to JSON data sent to Cesium client.
			// TODO: Potentially incorporate variable maximum distance depending
			//           on 2D-Cartesian distance from runway's final endpoint.
			// -- Matt Watson
			$exceeds = False;

			//ECL coords and slope
			$a = $ecl[0] / 131239;
			$b = $ecl[1] / 77136;
			$c = $ecl[2] / 131239;
			$d = $ecl[3] / 77136;
			$m = ($d - $b) / ($c - $a);
			//degrees of latitude and logitude converted to feet
			$lat1 = 0;
			$lat2 = 0;
			$long1 = 0;
			$long2 = 0;
			$dataSlope = 0;
			foreach ($data as $datum) {
				if ($lat1 == 0) {
					$long1 = $datum->longitude;
					$lat1 = $datum->latitude;
					continue;
				}
				$long2 = $datum->longitude / 131239;
				$lat2 = $datum->latitude / 77136;
				$m2 = ($lat1 - $lat2) / ($long1 - $long2);
				// line is nearly parallel with ECL to within 0.1 radians
				if (abs(atan(($m - $m2)/(1 + ($m * $m2)))) < 0.1) {
					$continueInner = False;
					foreach ($data as $datum2) {
						if ($continueInner == False) {
							if ($datum2->latitude == $datum->latitude) {
								$continueInner = True;
							}
							continue;
						}
						$x = $datum2->longitude / 131239;
						$y = $datum2->latitude / 77136;
						//distance in feet
						$distance = abs($y - ($m * $x) - $b + ($m * $a)) / sqrt(1 + ($m * $m));
						//if dist > 100 ft from ECL
						if ($distance > 100) {
							$exceeds = True;
							break;
						}
					}
					break;
				}
				$lat1 = $lat2;
				$long1 = $long2;
			}

			//$flightStr .= $airport; //current
			//$flightStr .= $previous; //$previous
			//$flightStr .= $next; //next

			//add the array to the output. key is the flight id and the value is an array of points.
			// $output["f$flightID"]=$flightStr;
			array_push($colPosData,$flightPosData);
		}
		$output->setPosData($colPosData);

		// echo "<pre>";
		$json = json_encode($output);



		return view('turnToFinalDisplay')->withData($json);
	}

	private function timeToSeconds($inTime)
	{
		//inTime is provided as hh:mm:ss, so we split by ':'
		$timeArray = explode(":",$inTime);

		//convert the time to seconds. hh*60^2 + mm 8 60 + ss
		$seconds = $timeArray[0]*pow(60,2)+$timeArray[1]*60+$timeArray[2];
		return $seconds;
	}

	public function viewFlights(Request $request)
	{

		$startDate = $request->input('startDate');
		$endDate = $request->input('endDate');

		$flightList =  FID::where('date','>=',$startDate)->where('date','<=',$endDate)->get();

		return view('turnToFinal')->withFlights($flightList);

	}



	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		//
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create()
	{
		//
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store()
	{
		//
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($id)
	{
		//
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function edit($id)
	{
		//
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($id)
	{
		//
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($id)
	{
		//
	}
}
class FlightDataCollection
{
	public $is3D;
	public $eclData;
	public $posData;

	function __construct()
	{

	}
	public function setPosData($in)
	{
		$this->posData=$in;
	}

	public function setEclData($in)
	{
		$this->eclData = $in;
	}

	public function setIs3D($in)
	{
		$this->is3D = $in;
	}
}

<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Carbon\Carbon;
use GuzzleHttp\Client;
use \BreatheCode\BreatheCodeLogger;
use BreatheCode\BCWrapper as BC;

BC::init(BREATHECODE_CLIENT_ID, BREATHECODE_CLIENT_SECRET, BREATHECODE_HOST, API_DEBUG);
BC::setToken(BREATHECODE_TOKEN);

\AC\ACAPI::start(AC_API_KEY);
\AC\ACAPI::setupEventTracking('25182870', AC_EVENT_KEY);

return function($api){

	$api->addTokenGenerationPath();

	//deprecated
	$api->get('/all', function (Request $request, Response $response, array $args) use ($api) {

		$content = $api->db['sqlite']->event();
		$includeRecurrents = false;
		$includeUnlisted = false;
		if(isset($_GET['type'])) $content = $content->where('type',explode(",",$_GET['type']));
		if(isset($_GET['location'])) $content = $content->where('location_slug',explode(",",$_GET['location']));
		if(isset($_GET['lang'])) $content = $content->where('lang',explode(",",$_GET['lang']));
		if(isset($_GET['recurrent'])) $includeRecurrents = $_GET['recurrent'] == 'true';
		if(isset($_GET['unlisted'])) $includeUnlisted = $_GET['unlisted'] == 'true';
		if(isset($_GET['status'])){
			if($_GET['status']=='upcoming') {
				if($includeUnlisted) $content = $content->whereNot('status','draft');
				else $content = $content->where('status','published');
				$content = $content->orderBy( 'event_date', 'DESC' )->fetchAll();
				//compare dates from today and return non-recurring instances
				$content = array_values(array_filter($content, function($evt) use ($includeRecurrents){
					return $evt->event_date >= date("Y-m-d") && ($includeRecurrents || empty($evt->recurrent_type) || $evt->recurrent_type == 'one_time');
				}));
				return $response->withJson($content);
			} else if($_GET['status']=='past') {
				if($includeUnlisted) $content = $content->whereNot('status','draft');
				else $content = $content->where('status','published');
				$content = $content->orderBy( 'event_date', 'DESC' )->fetchAll();
				//compare dates from today and return non-recurring instances
				$content = array_values(array_filter($content, function($evt) use ($includeRecurrents){
					return ($evt->event_date < date("Y-m-d")) && ($includeRecurrents || empty($evt->recurrent_type) || $evt->recurrent_type == 'one_time');
				}));
			} else {
				$content = $content->where('status',explode(",",$_GET['status']));
				$content = $content->orderBy( 'event_date', 'DESC' )->fetchAll();
				//only return non-recurring events
				$content = array_values(array_filter($content, function($evt) use ($includeRecurrents){
					return ($includeRecurrents || empty($evt->recurrent_type) || $evt->recurrent_type == 'one_time');
				}));
			}
		}

	    return $response->withJson($content);
	});

	$api->get('/hook/generate_next_recurrent_events', function (Request $request, Response $response, array $args) use ($api) {

		$results = [];
		$parentEvents = $api->db['sqlite']->event()
					->where('status','published')
					->whereNot('recurrent_type','one_time')->fetchAll();

		//get all recurrent events
		$results[] = count($parentEvents)." recurrent events were found";
		foreach($parentEvents as $parent){

			//get all childs (actual instances of the events)
			$childEvents = $api->db['sqlite']->event()
						->where('status','published')
						->where('parent_event', $parent->id)->fetchAll();
			$childEvents = array_values(array_filter($childEvents, function($evt){
				return $evt->event_date >= date("Y-m-d");
			}));
			$results[] = count($childEvents)." upcoming for the events for ".$parent->title." were found";

			//skip parent events with upcoming childs because we are only creating chils if there is none
			if(count($childEvents) > 0){
				$results[] = "Skipping ".$parent->title." because it already has an upcoming event";
				continue;
			}

			$results[] = "Generating new upcoming events for ".$parent->title." (".$props->recurrent_type.")";
			$props = $parent->getData();
			if($props->recurrent_type == "every_week"){
				$dayOfWeek = $row->event_date->format('l');
				$props['event_date'] = new DateTime("next ".$dayOfWeek);
				$props['created_at'] = date("Y-m-d H:i:s");
				$results[] = "New child generated on ".$props['event_date'];
				$row = $api->db['sqlite']->createRow('event', $props);
			}
			else{
				$response = $response->withStatus(400);
				$results[] = "ERROR! The event ".$parent->title." has an invalid recurrent_type: (".$props->recurrent_type.")";
			}

		}

	    return $response->withJson($results);
	});

	$api->get('/redirect', function (Request $request, Response $response, array $args) use ($api) {

		$content = $api->db['sqlite']->event();
		if(isset($_GET['type'])) $content = $content->where('type',explode(",",$_GET['type']));
		if(isset($_GET['location'])) $content = $content->where('location_slug',explode(",",$_GET['location']));
		if(isset($_GET['lang'])) $content = $content->where('lang',explode(",",$_GET['lang']));

		if(isset($_GET['status'])){
			if($_GET['status']=='upcoming') {
				$content = $content->where('status','published');
				$content = $content->orderBy( 'event_date', 'DESC' )->fetchAll();

				$content = array_filter($content, function($evt){
					return ($evt->event_date >= date("Y-m-d"));
				});
			}
			else if($_GET['status']=='past') {
				$content = $content->where('status','published');
				$content = $content->orderBy( 'event_date', 'DESC' )->fetchAll();
				$content = array_filter($content, function($evt){
					return ($evt->event_date < date("Y-m-d"));
				});
			}
		}

		if(!is_array($content)) $content = $content->orderBy( 'event_date', 'DESC' )->fetchAll();

		if(isset($content[0])) return $response->withRedirect($content[0]->url);
		else{
			$fallback = (isset($_GET['fallback'])) ? $_GET['fallback'] : null;
			//$api->sendMail("a@4geeks.us", "Broken redirect for events", "The following query returned no events: ".print_r($_GET));
			if($fallback) return $response->withRedirect($fallback)->withStatus(302);
			else return $response->withStatus(404);
		}
	});

	$api->get('/{event_id}', function(Request $request, Response $response, array $args) use ($api) {

		if(empty($args['event_id'])) throw new Exception('Invalid param event_id', 400);

		$row = $api->db['sqlite']->event()->where('id',$args['event_id'])->fetch();

		return $response->withJson($row);
	});

	//update
	$api->post('/{event_id}', function(Request $request, Response $response, array $args) use ($api) {
		if(empty($args['event_id'])) throw new Exception('Invalid param event_id', 400);

		$event = $api->db['sqlite']->event()->where('id',$args['event_id'])->fetch();

        $parsedBody = $request->getParsedBody();
        $desc = $api->optional($parsedBody,'description')->bigString();
        if($desc) $event->description = $desc;

        $title = $api->optional($parsedBody,'title')->smallString();
        if($title) $event->title = $title;

        $url = $api->optional($parsedBody,'url')->url();
        if($url) $event->url = $url;

        $capacity = $api->optional($parsedBody,'capacity')->int();
        if($capacity) $event->capacity = $capacity;

        $logo = $api->optional($parsedBody,'logo_url')->url();
        if($logo) $event->logo_url = $logo;

        $val = $api->optional($parsedBody,'invite_only')->bool();
        if($val) $event->invite_only = $val;

        $val = $api->optional($parsedBody,'event_date')->date();
        if($val) $event->event_date = $val;

        $val = $api->optional($parsedBody,'type')->enum(EventFunctions::$types);
        if($val) $event->type = $val;

        $val = $api->optional($parsedBody,'status')->enum(EventFunctions::$status);
        if($val) $event->status = $val;

        $val = $api->optional($parsedBody,'address')->smallString();
        if($val) $event->address = $val;

        $val = $api->optional($parsedBody,'location_slug')->slug();
        if($val) $event->location_slug = $val;

        // TODO: Add latitude longitude to the event API
        // $val = $api->optional($parsedBody,'latitude')->slug();
        // if($val) $event->latitude = $val;
        // $val = $api->optional($parsedBody,'latitude')->slug();
        // if($val) $event->latitude = $val;

        $val = $api->optional($parsedBody,'lang')->slug();
        if($val) $event->lang = $val;
        $val = $api->optional($parsedBody,'city_slug')->slug();
        if($val) $event->city_slug = $val;
        $val = $api->optional($parsedBody,'banner_url')->url();
        if($val) $event->banner_url = $val;
        $val = $api->optional($parsedBody,'recurrent_type')->enum(EventFunctions::$recurrentTypes);
        if($val){
        	$event->recurrent_type = $val;
			$event->recurrent = ($val != 'one_time') ? true : false;
        }

        $event->save();

		if($event->recurrent){
			$eventChilds = $api->db['sqlite']->event()
						->where('status','published')
						->where('parent_event', $event->id)->fetchAll();
			$eventChilds = array_values(array_filter($eventChilds, function($evt){
				return $evt->event_date >= date("Y-m-d");
			}));
			foreach($eventChilds as $child) $child->delete();

			if($event->status != 'draft'){
				$props = $event->getData();
				$props['parent_event'] = $event->id;
				$props['recurrent'] = false;
				unset($props['id']);
				$props['recurrent_type'] = 'one_time';
				if($event->recurrent_type == "every_week"){
					$dayOfWeek = DateTime::createFromFormat('Y-m-d H:i:s', $event->event_date)->format('l');
					$props['event_date'] = new DateTime("next ".$dayOfWeek);
				}
				$props['created_at'] = date("Y-m-d H:i:s");

				$child = $api->db['sqlite']->createRow('event', $props);
				$child->save();
			}
		}

		return $response->withJson($event);
	})->add($api->auth());

	$api->delete('/{event_id}', function(Request $request, Response $response, array $args) use ($api) {

		if(empty($args['event_id'])) throw new Exception('Invalid param event_id', 400);

		$row = $api->db['sqlite']->event()->where('id',$args['event_id'])->fetch();
		if($row){
			if($row->recurrent){
				$eventChilds = $api->db['sqlite']->event()
							->where('status','published')
							->where('parent_event', $row->id)->fetchAll();
				$eventChilds = array_values(array_filter($eventChilds, function($evt){
					return $evt->event_date >= date("Y-m-d");
				}));
				foreach($eventChilds as $child) $child->delete();
			}

			$row->delete();
		}
		else throw new Exception('Event not found');

		return $response->withJson([ "code" => 200 ]);
	})->add($api->auth());

	$api->put('/', function(Request $request, Response $response, array $args) use ($api) {
        $parsedBody = $request->getParsedBody();

        // $status = $api->optional($parsedBody,'status')->smallString();
        // if($status) throw new Exception('The status can only be a draft, you can later update the shift status on another request', 400);

        $desc = $api->validate($parsedBody,'description')->bigString();
        $title = $api->validate($parsedBody,'title')->smallString();
        $url = $api->validate($parsedBody,'url')->url();
        $capacity = $api->validate($parsedBody,'capacity')->int();
        $logo = $api->optional($parsedBody,'logo_url')->url();
        $type = $api->validate($parsedBody,'type')->enum(EventFunctions::$types);
        $city = $api->validate($parsedBody,'city_slug')->slug();
        $location = $api->validate($parsedBody,'location_slug')->slug();
        $lang = $api->validate($parsedBody,'lang')->enum(['en','es']);
        $banner = $api->validate($parsedBody,'banner_url')->url();
        $address = $api->validate($parsedBody,'address')->smallString();
        // $longitude = $api->validate($parsedBody,'latitude')->smallString();
        // $latitude = $api->validate($parsedBody,'longitude')->smallString();
        $date = $api->validate($parsedBody,'event_date')->date();
        $val = $api->validate($parsedBody,'invite_only')->bool();
        $recurrentType = $api->optional($parsedBody,'recurrent_type')->enum(EventFunctions::$recurrentTypes);
        $recurrent = (!empty($recurrentType) && $recurrentType != 'one_time');

        $props = [
			'type' => EventFunctions::getType($type),
			'description' => $desc,
			'title' => $title,
			'url' => $url,
			'capacity' => $capacity,
			'logo_url' => $logo,
			'location_slug' => $location,
			'city_slug' => $city,
			'lang' => $lang,
			'recurrent' => $recurrent,
			'parent_event' => null,
			'recurrent_type' => $recurrentType,
			'status' => EventFunctions::getStatus('draft'),
			'banner_url' => $banner,
			'address' => $address,
			// 'longitude' => $longitude,
			// 'latitude' => $latitude,
			'invite_only' => $val,
			'event_date' => DateTime::createFromFormat('Y-m-d H:i:s', $date),
			'created_at' => date("Y-m-d H:i:s")
		];
		$row = $api->db['sqlite']->createRow('event', $props);
		$row->save();

        return $response->withJson($row);
	})->add($api->auth());

	$api->get('/user/{user_email}', function(Request $request, Response $response, array $args) use ($api) {

		if(empty($args['user_email'])) throw new Exception('Param user_email not found', 400);

		$user = BC::getUser(['user_id' => urlencode($args['user_email'])]);

		return $response->withJson($user);
	})->add($api->auth());

	$api->put('/{event_id}/checkin', function(Request $request, Response $response, array $args) use ($api) {

        if(empty($args['event_id'])) throw new Exception('Invalid param event_id', 400);

        $event = $api->db['sqlite']->event()->where('id',$args['event_id'])->fetch();
        if(!$event) throw new Exception('Event not found', 401);

        $parsedBody = $request->getParsedBody();
        $email = $api->validate($parsedBody,'email')->email();

        $contact = \AC\ACAPI::getContactByEmail($email);
        if(empty($contact)) throw new Exception('The user is not registered into Active Campaign', 401);

        $row = $api->db['sqlite']->event_checking()->where( 'event_id', $args['event_id'] )->fetchAll();
		foreach($row as $checkin)
			if($checkin['email'] == $email)
				throw new Exception('The user has already checked in', 400);

        $props = [
			'event_id' => $args['event_id'],
			'email' => $email,
			'created_at' => date("Y-m-d H:i:s")
		];

		$row = $api->db['sqlite']->createRow('event_checking', $props);
		$row->save();

		$user = null;
		$trackOnBreathecode = true;
		try{
			$user = BC::getUser(['user_id' => urlencode($email)]);
		}
		catch(Exception $e){ $trackOnBreathecode = false; }
        BreatheCodeLogger::logActivity([
            'slug' => 'public_event_attendance',
            'user' => ($user) ? $user : $email,
            'track_on_log' => $trackOnBreathecode,
            'data' => $event->title
        ]);

        return $response->withJson($row);
	})->add($api->auth());

	$api->get('/{event_id}/checkin', function(Request $request, Response $response, array $args) use ($api) {

        if(empty($args['event_id'])) throw new Exception('Invalid param event_id', 400);

        $event = $api->db['sqlite']->event()->where('id',$args['event_id'])->fetch();
        if(!$event) throw new Exception('Event not found', 401);

        $rows = $api->db['sqlite']->event_checking()->where( 'event_id', $args['event_id'] )->fetchAll();

        return $response->withJson($rows);
	})->add($api->auth());

	//publish event on eventbrite
	$api->post('/{event_id}/eventbrite', function(Request $request, Response $response, array $args) use ($api) {

        if(empty($args['event_id'])) throw new Exception('Invalid param event_id', 400);

        $event = $api->db['sqlite']->event()->where('id',$args['event_id'])->fetch();
        if(!$event) throw new Exception('Event not found', 401);

        $client = new Client();
		$r = $client->request('POST', 'https://hooks.zapier.com/hooks/catch/2995810/c3tlmv/', [
		    'body' => json_encode($event)
		]);

        return $response->withJson(["status" => "ok"]);
	})->add($api->auth());

	$api->post('/active_campaign/user', function(Request $request, Response $response, array $args) use ($api) {

        $parsedBody = $request->getParsedBody();
        $email = $api->validate($parsedBody,'email')->email();
        $firstName = $api->validate($parsedBody,'first_name')->smallString();
        $lastName = $api->validate($parsedBody,'last_name')->smallString();

        $contact = \AC\ACAPI::getContactByEmail($email);
        if(!empty($contact)) throw new Exception('The user is already registered on Active Campaign', 400);

        $contact = \AC\ACAPI::createContact($email, [
    		"first_name"        => $firstName,
    		"last_name"         => $lastName,
    		"tags" => \AC\ACAPI::tag('event_signup')
        ]);

        return $response->withJson(["status" => "ok"]);
	})->add($api->auth());

	return $api;
};
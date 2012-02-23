<?php

//set semester start and end dates....format: (day--optional--if included, must be 1-31) (full month name)
//dates can overlap
//academic calendars can be found here: http://registrar.fiu.edu/index.php?id=88
$start_spring = "10 January";
$end_spring= "30 April";

$start_summerA = "1 May";
$end_summerA= "23 June";

$start_summerB = "24 June";
$end_summerB= "13 August";

$start_summerC = "1 May";
$end_summerC= "13 August";

$start_fall = "14 August";
$end_fall= "10 December";


// $weekend --
// default: boolean true, adds Sat/Sun to table if there are classes on Sat/Sun
// boolean false excludes sat/sun even if there are classes on those days
//
// $category_slug --
// default: 'classes'
// if category slug changes, this value must be changed
// it is not advisable to change this value unless necessary--all magic fields associated with 'classes' 
// must be retain association to any new slug
//
// $special -- (refers to date-specific courses)
// default: boolean true, places special events at bottom of table
// if value is 'top', special events are places at the top of the table
// if value is boolean false, special events are not shown at all
//
//
function class_calendar( $weekend = true, $category_slug = 'classes',  $special = true ) {

//declare global semester dates set above
//need to do this to access them in function
	global 
	$start_spring,$end_spring,
	$start_summerA,$end_summerA,
	$start_summerB,$end_summerB,
	$start_summerC,$end_summerC,
	$start_fall,$end_fall;
		
	$ts_current = ( strtotime(date('j F Y') ) );
	$sat_sun = '';

	function date_range( $start, $end ) {
		$start = implode( ' ', array_reverse( explode( ' ', $start ) ) );
		$end = implode( ' ', array_reverse( explode( ' ', $end ) ) );
		return $start.'-'.$end;
	}	

	if ( $special ) {
		//retrieve dates for special courses
		$special_query = new WP_Query( array ( 'meta_key' => 'special_course_detail_date' ) );
		while ( $special_query->have_posts() ) : $special_query->the_post();
			$dates = get_group('Special Course Details'); 
			if ( $dates ) {
				foreach ( $dates as $date ) {
					$dates_array = $dates[1][special_course_detail_date];
				}
				sort( $dates_array, SORT_NUMERIC );
				$events = array( get_the_ID() => $dates_array );
			}
		endwhile;
		
		if ( is_array( $events ) ) {
			foreach ( $events as $ID=>$dates ) {
				foreach ( $dates as $date ) {
					$tmstring = strtotime( $date );
					if( $tmstring >= strtotime( "-1 day" ) ) {
						$day = date('D', $tmstring );
						$m_y = rstrstr( $date, ',' );
						$yr = ltrim( ( strstr( $date, ',' ) ), ', ' );
						
						global $post;
						query_posts( 'p='.$ID );
						while ( have_posts() ) : the_post();
							$classTime = "<br />".get('currently_offered_detail_time');
							$classRoom = get('currently_offered_detail_room');
							if ( strlen( $classRoom ) > 0 ) {  $classRoom = '<br />'.$classRoom; }
							$profDetails = get_group('Instructor Details');
							$profs = '';
							if ( $profDetails ) {
								foreach ( $profDetails as $profDetail) {
									if ( $profDetail['instructor_detail_current_instructor'][1] ) {
										$person_post_ID = $profDetail['instructor_detail_name'][1];
										$person_post = get_post( $person_post_ID );
										$name = $person_post->post_title;
										$prof = "<div class='inline-block'>".$name."</div>";
										$profs .= $prof." &amp; ";
									}
								}
								if ( $profs ) { $profs = "<br />".rtrim( $profs, '&amp; '); }
							}
							
							$info = '<a href="'.get_permalink( $post->ID ).'">'.get_the_title().$classTime.$profs.$classRoom.'</a>';
						endwhile;
								
						//if element is not empty, add another date to string
						//without replacing first date
						if ( $special_events[$day][$ID] ) { 
							$old = explode( '</span>', $special_events[$day][$ID] );
							$f_date = $old[0].', <div class="inline-block">'.$m_y.'</div></span>';
							$special_events[$day][$ID] = $f_date.' '.$info;
						} else {
							$f_date = '<span class="date">'.$m_y.'</span>'; 
							$special_events[$day][$ID] = $f_date.' '.$info;
							if ( $day == 'Sat' ) { $sat_sun .= 'Sat'; }
							if ( $day == 'Sun' ) { $sat_sun .= 'Sun'; }
						}
					}
				}
			}
		}
	}

	$semesters = array(
		'Spring'=>array( strtotime( $start_spring ), strtotime( $end_spring ), date_range( $start_spring, $end_spring ) ),
		'Summer A'=>array( strtotime( $start_summerA ), strtotime( $end_summerA ), date_range( $start_summerA, $end_summerA ) ),
		'Summer B'=>array( strtotime( $start_summerB ), strtotime( $end_summerB ), date_range( $start_summerB, $end_summerB ) ),
		'Summer C'=>array( strtotime( $start_summerC ), strtotime( $end_summerC ), date_range( $start_summerC, $end_summerC ) ),
		'Fall'=>array( strtotime( $start_fall ), strtotime( $end_fall ), date_range( $start_fall, $end_fall ) ),
	);

	foreach ( $semesters as $semester=>$info ) {
		if ($info[0] <= $ts_current && $info[1] >= $ts_current){

			$current_semesters[] = array( $semester, $info[2] );

		}
	}

	global $post;
	$days = array( 'Mon'=>'', 'Tue'=>'', 'Wed'=>'', 'Thu'=>'', 'Fri'=>'' );
	if ( $weekend ) { $days['Sat'] = ''; $days['Sun'] = ''; } 
	
	$args = array ( 'category_name'=>$category_slug, );
	
	$query = new WP_Query( $args );
	while ( $query->have_posts() ) : $query->the_post();
	
		$current_details = get_group('Currently Offered Details'); 
		foreach($current_details as $current_detail){
			$offered = $current_detail['currently_offered_detail_semester'][1];
			
			//find out which semester the class details apply to
			//hides outdated information and information entered ahead of time for future semesters
			if (strlen($offered) > 0 ) { 
				//j=current date (1-31), F=full name of current month (eg.January), Y=current yr (YYYY)
				//see date() function: http://php.net/manual/en/function.date.php
				//creates timestamp from current date
				//semester name reformatted: no spaces, lowercase first letter
				$semester = substr($offered, 0, -4);
				$year = substr($offered, -4);
	
				foreach ( $current_semesters as $is_current) {
					if ( stristr( $semester, $is_current[0] ) ) {
						//assign each field a variable name
						$classTime = $current_detail['currently_offered_detail_time'][1];
						
						if ( strpos( $classTime, ':' ) ) {
							//if string is correctly formatted,
							//get hour (everything before ':')
							$hr = rstrstr($classTime, ':');
							$min = strstr($classTime, ':');
							$min = $min[1].$min[2];
						}
						else { 
							//if string is incorrectly formatted (doesn't have colon), 
							//get everything before '-'
							$hr = rstrstr($classTime, '-'); 
							$min = '00';
						}
						
						if ( ! $min ) { $min = '00'; }
						if ( strlen( $hr ) > 0 ) {
							$simple = str_replace ( array( 'A', 'P', 'a', 'p'), '@', $classTime );
							$pos = strpos($simple, '@');
							$a_p = strtoupper( $classTime[$pos] );
							$ref = get('permanent_info_reference_number');
							$title = get_the_title();
							$classDays = $current_detail['currently_offered_detail_days'][1];
							$classCampus = $current_detail['currently_offered_detail_campus'][1];
							$classRoom = $current_detail['currently_offered_detail_room'][1];
							if ( strlen( $classRoom ) > 0 ) {  $classRoom = '<br />'.$classRoom; }
							$profDetails = get_group('Instructor Details');
							$profs = '';
							if ( $profDetails ) {
								foreach ( $profDetails as $profDetail) {
									if ( $profDetail['instructor_detail_current_instructor'][1] ) {
										$person_post_ID = $profDetail['instructor_detail_name'][1];
										$person_post = get_post( $person_post_ID );
										$name = $person_post->post_title;
										$prof = "<div class='inline-block'>".$name."</div>";
										$profs .= $prof." &amp; ";
									}
								}
								if ( $profs ) { $profs = "<br />".rtrim( $profs, '&amp; '); }
							}
							
							if ( $a_p == 'P' ) { $mhr = ( $hr + 12 ); }
							else { $mhr = $hr; }
							
							foreach ($classDays as $day) {
								$courses[$mhr.$min][$day][$post->ID] = '<span class="time">'.$classTime.'</span><a href="'.get_permalink( $post->ID ).'">'.$ref.'<br />'.$title.$profs.$classRoom.'</a>'; 
								if ( $day == 'Sat' ) { $sat_sun .= 'Sat'; }
								if ( $day == 'Sun' ) { $sat_sun .= 'Sun'; }
							}
						}
					}
	
				}
				
			//end if srtlen offered > 0
			}
				
		//end foreach
		} 
	
	endwhile;
	
	if ( is_array( $special_events ) ) {
		if ( $special === 'top' ) { 
			$courses[0] = $special_events;
		} else {
			$courses[99999] = $special_events;
		}
	}
	if ( $courses ) { 
		ksort( $courses ); 
	}
	if ( $weekend && $sat_sun ) { $weekend = $sat_sun; }
	draw_course_table( $courses, $current_semesters, $weekend );
	wp_reset_query();
}


//if weekend parameter is true, sat & sun will be added to table
function draw_course_table ( $courses, $semesters, $weekend ) {
	if ( ! $courses ) { $courses = array(); $courses[0][0][0] = '<p>&nbsp;</p>'; }
	//array of current course IDs passed
		//from class_calendar function as multidimensional array
		$days = array ( 'Mon', 'Tue', 'Wed', 'Thu', 'Fri' );
		if ( $weekend ) {
			if ( stristr ( $weekend, 'Sat' ) && ! strstr( $weekend, 'Sun' ) ) { 
				$z = count($days);
				$days[$z] = 'Sat'; 
			}
			if ( stristr ( $weekend, 'Sun' ) ) { 
				$z = count($days);
				$days[$z] = 'Sat';
				$days[$z+1] = 'Sun';
			}

		}
	?>
	
	<table cellspacing="0" class='classes-table'>
		
		<?php 
			$semester_names = "Current Courses: ";
			foreach ( $semesters as $semester ) {
				$semester_names .= $semester[0]/*."(".$semester[1].")*/." &amp; ";
			}		
		?>
		<caption><?php echo rtrim( $semester_names, ' &amp; ' ); ?></caption>
		<colgroup>
			<col id="Mon" />
			<col id="Tue" />
			<col id="Wed" />
			<col id="Thu" />
			<col id="Fri" />
		  <?php if ( stristr ( $weekend, 'Sat' ) || strstr( $weekend, 'Sun' ) ) { ?>
		  	<col id="Sat" />
          <?php }
		  if ( stristr ( $weekend, 'Sun' ) ) { ?>
		  	<col id="Sun" />
		  <?php } ?>
		</colgroup>
		<thead>
		  <tr>
		  	<th scope = 'col'>Mon</th>
		  	<th scope = 'col'>Tues</th>
		  	<th scope = 'col'>Wed</th>
		  	<th scope = 'col'>Thurs</th>
		  	<th scope = 'col'>Fri</th>
		  <?php if ( stristr ( $weekend, 'Sat' ) || strstr( $weekend, 'Sun' ) ) { ?>
		  	<th scope = 'col'>Sat</th>
		  <?php }
		  if ( stristr ( $weekend, 'Sun' ) ) { ?>
		  	<th scope = 'col'>Sun</th>
		  <?php }?>
		  </tr>
		</thead>
		<tbody>
	<?php
	foreach ( $courses as $time_slot=>$info ) {
		echo "
		<tr>";
		$i = 1;
		$num_days = count($days);
		while ( $i <= $num_days ) {
			$x = ( $i - 1 );
			echo "
			<td class='".$days[$x]."' style='border: 1px solid;'>";
				foreach ( $info as $day=>$information ) {
					if ( $day == $days[$x] ) {
						$last = count( $information );
						$count = 0;
						foreach ( $information as $single ) {
							$count++;
							echo "<span class='hover";
							if ( $last !== $count  ) { 
								echo " multiple"; }
							echo "'>".$single."</span>";
						}
					}
				}
			echo "
			</td>";
			$i++;
		}
		
		echo "
		</tr>";
	}
	?>
	
		 </tbody>
	</table>	

<?php
if ( $courses[0][0][0] == "<p>&nbsp;</p>" ) { echo "<p>No courses listed this semester.</p>"; }
}


//add calendar css
add_action('wp_print_styles', 'gis_calendar_style');

//function to add css for calendar
function gis_calendar_style() {
	$root = get_template_directory_uri();
	wp_register_style('screen_style', $root.'/calendar/css/weekly_screen.css' );
	wp_enqueue_style( 'screen_style');
}


function rstrstr($haystack,$needle, $start=0) {
	return substr($haystack, $start,strpos($haystack, $needle));
}
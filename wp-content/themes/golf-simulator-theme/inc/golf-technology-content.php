<?php

if (!defined('ABSPATH')) {
    exit;
}

function golf_simulator_theme_golf_technology_sections() {
    return array(
        'technology-moving-swing-plate' => array(
            'number' => '01', 'label' => 'Motion Plate & Multi-Surface Play', 'featured' => true,
            'aliases' => array('technology-multi-surface-play'),
            'text' => 'Experience realistic course conditions instead of hitting every shot from a perfectly flat surface. The Motion Plate recreates uphill, downhill, and sidehill lies, while multiple hitting surfaces simulate fairway, rough, and bunker conditions.',
            'details' => array('Moves in 64 directions', 'Up to 56,000 possible lies', 'Realistic uphill, downhill, and sidehill shots', 'Fairway, rough, and bunker surfaces', 'More realistic club-to-turf interaction'),
            'video_id' => 'wLWPu46TT68', 'video_title' => 'The TwoVisionNX Motion Plate — GOLFZON',
        ),
        'technology-auto-tee' => array(
            'number' => '02', 'label' => 'Auto-Tee Technology', 'featured' => true,
            'text' => 'Spend less time setting up and more time playing. The automatic ball-feed system delivers and tees the next ball after each shot, helping keep your practice session or round moving smoothly. Players can also adjust tee height to match their club and preferred setup.',
            'video_id' => 'sLkbwrUpKHg', 'video_title' => "GOLFZON's Auto-Tee is a Game Changer!",
        ),
        'technology-mapped-courses' => array(
            'number' => '03', 'label' => '300+ Golf Courses', 'featured' => true,
            'text' => 'Play more than 300 virtually recreated golf courses from around the world, including famous destinations such as St Andrews, Pebble Beach, Kiawah Island, Harbour Town, PGA National, and more. Play somewhere new each visit or return to one of your favorites.',
            'video_id' => 'JT1JVPD8Y-8', 'video_title' => "A Troon North Pro's Guide to Playing Pinnacle — GOLFZON Course Guide",
        ),
        'technology-shot-analysis' => array(
            'number' => '04', 'label' => 'Advanced Shot & Club Analysis', 'featured' => true,
            'aliases' => array('technology-shot-tracking', 'technology-club-data'),
            'text' => 'Get detailed feedback after every shot to better understand your swing and ball flight. Depending on the practice mode, measurements can include:',
            'details' => array('Carry and total distance', 'Ball speed', 'Club speed', 'Launch angle', 'Launch direction', 'Attack angle', 'Face angle', 'Dynamic loft', 'Face-to-path', 'Smash factor', 'Club path', 'Spin axis', 'Back spin', 'Spin rate', 'Apex'),
            'after' => 'TwoVision NX uses high-speed camera technology to capture ball and club information and reproduce realistic shot shape and trajectory.',
            'video_id' => 'f-atsI5iRdY', 'video_title' => 'GOLFZON TwoVisionNX Demo',
        ),
        'technology-performance-tracking' => array(
            'number' => '05', 'label' => 'Performance Tracking & Swing Replay',
            'aliases' => array('technology-swing-replay'),
            'text' => 'Go beyond a single shot and see how your game develops over time. Review previous practice sessions, compare club performance, examine shot dispersion, and use swing replay alongside your performance data to connect your movement with the resulting ball flight. It is a powerful way to practice with purpose instead of simply hitting balls.',
            'video_id' => 'HFVx1WAdYHg', 'video_title' => 'How Good is a PGA TOUR Player on a GOLFZON Simulator?',
        ),
        'technology-driving-range' => array(
            'number' => '06', 'label' => 'Complete Practice Center', 'featured' => true,
            'aliases' => array('technology-approach-practice', 'technology-pitch-chip', 'technology-short-game'),
            'text' => 'Work on every part of your game with dedicated practice environments. Whether you are warming up, working on your swing, or preparing for your next round, the practice tools help make every session productive.',
            'subsections' => array(
                'Driving Range' => 'Practice full swings while receiving immediate feedback on ball flight, launch, speed, spin, and club performance.',
                'Approach Practice' => 'Sharpen your approach game by practicing different distances, targets, lies, and green conditions.',
                'Pitch, Chip & Short Game' => 'Build confidence around the green with focused practice from a variety of distances and lies.',
            ),
            'video_id' => '33gK1fA_a6Q', 'video_title' => 'GOLFZON TwoVisionNX Feature Upgrade: Shot Practice Mode',
        ),
        'technology-precision-putting' => array(
            'number' => '07', 'label' => 'Precision Putting & LED Guide', 'featured' => true,
            'aliases' => array('technology-led-putting-guide', 'technology-putting-practice', 'technology-putt-off-green'),
            'text' => 'Practice putting with technology designed to make the virtual green feel more natural and intuitive. Work on distance control, direction, speed, break, uphill and downhill putts, and breaking putts. The illuminated LED putting guide provides a visual reference for the suggested putting direction, helping players better understand the virtual green. Players can also choose the putter from eligible areas just off the green for creative Texas-wedge-style shots.',
            'video_id' => 'TN4c3LV-Svw', 'video_title' => 'GOLFZON TwoVisionNX Standard Simulator Demo',
        ),
        'technology-mobile-app' => array(
            'number' => '08', 'label' => 'GOLFZON Global Mobile App',
            'text' => "Take your game beyond the simulator. With a GOLFZON account and the Global App, players can access information such as previous rounds, scores, practice sessions, club performance, swing videos, statistics, course information, and GOLFZON activity. Your practice doesn't have to end when you leave Tee Time Nexus.",
            'video_id' => 'U3uM0KQ9luE', 'video_title' => 'GOLFZON Global App Walkthrough',
        ),
        'technology-network-play' => array(
            'number' => '09', 'label' => 'Network Play',
            'text' => "Golf doesn't have to stop at your simulator bay. GOLFZON Network Play allows compatible simulators to connect for multiplayer golf, head-to-head competition, tournaments, and real-time scoring. Compete with friends or connect with other golfers through GOLFZON's global simulator network.",
            'video_id' => 'G1Z1Qp03KVo', 'video_title' => 'Top College Golfers Play 1 vs. 1 Match on GOLFZON Simulator Network Play',
        ),
        'technology-unreal-graphics' => array(
            'number' => '10', 'label' => 'Unreal Engine 5 Graphics', 'featured' => true,
            'text' => 'Step into an immersive virtual golf environment powered by Unreal Engine 5. Detailed terrain, greens, bunkers, vegetation, lighting, course surroundings, moving flags, divots, and other visual elements help bring each virtual course to life. The result is designed to feel less like a video game and more like being on the course.',
            'video_id' => 'GYu-6DNR-Aw', 'video_title' => 'THE BRAND NEW TWOVISIONNX — GOLFZON',
        ),
        'technology-zero-latency' => array(
            'number' => '11', 'label' => 'Zero-Latency Gameplay',
            'text' => 'Keep your natural golfing rhythm. TwoVision NX is designed to minimize the delay between ball impact and the on-screen shot, creating an immediate visual response after contact. The fast response helps gameplay feel smooth, natural, and immersive without unnecessary interruptions between your swing and ball flight.',
            'video_id' => 'f-atsI5iRdY', 'video_title' => 'GOLFZON TwoVisionNX Demo',
        ),
        'technology-touchscreen' => array(
            'number' => '12', 'label' => 'Smart Player Controls & Course Information',
            'aliases' => array('technology-keypad', 'technology-course-info'),
            'text' => 'Control your experience without interrupting your game.',
            'subsections' => array(
                'Touchscreen Control' => 'Use the high-definition touchscreen kiosk to access courses, practice modes, player settings, simulator options, and other controls.',
                'Player Keypad' => 'Access common functions directly from the hitting area, including tee-height adjustments, mulligans, and player controls.',
                'Strategic Course Information' => 'Review distances, targets, landing areas, and course information before choosing your club and planning your shot.',
            ),
            'video_id' => 'JT1JVPD8Y-8', 'video_title' => "A Troon North Pro's Guide to Playing Pinnacle — GOLFZON Course Guide",
        ),
        'technology-arcade-plus' => array(
            'number' => '13', 'label' => 'Arcade Plus',
            'text' => 'Arcade Plus takes the simulator beyond traditional golf and turns it into an interactive entertainment experience for kids, families, friends, parties, and groups. When included in the facility configuration, it offers seven games:',
            'details' => array('Slope Golf', 'Dart Golf', 'Block Golf', 'ProBowl Showdown', 'Wild Wild West', 'Whack-A-Mole', 'Night Glow Range'),
            'after' => 'Whether you are an experienced golfer or have never picked up a club, Arcade Plus gives everyone another way to have fun at Tee Time Nexus.',
            'video_id' => 'idUNCdInBzo', 'video_title' => 'Watch GOLFZON Arcade Plus in Action',
        ),
    );
}

function golf_simulator_theme_golf_technology_media_id($saved_gifs, $feature_id) {
    $sections = golf_simulator_theme_golf_technology_sections();
    if (!is_array($saved_gifs) || !isset($sections[$feature_id])) {
        return 0;
    }
    foreach (array_merge(array($feature_id), $sections[$feature_id]['aliases'] ?? array()) as $candidate) {
        $attachment_id = absint($saved_gifs[$candidate] ?? 0);
        if ($attachment_id) {
            return $attachment_id;
        }
    }
    return 0;
}

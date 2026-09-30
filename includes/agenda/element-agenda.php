<?php
namespace CC\Brella;

/**
 * Bricks element: Brella Agenda. Reads the schedule cached by W3 Brella Integration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Agenda_Element extends \Bricks\Element {

	public $category = 'general';
	public $name     = 'brella-agenda';
	public $icon     = 'ti-calendar';
	public $scripts  = [ 'brellaAgendaInit' ];

	public function get_label() {
		return esc_html__( 'Brella Agenda', 'cryptocon-brella' );
	}

	public function get_keywords() {
		return [ 'brella', 'agenda', 'schedule', 'event', 'timetable', 'program' ];
	}

	public function enqueue_scripts() {
		Agenda::enqueue();
	}

	public function set_control_groups() {
		$this->control_groups['source']   = [ 'title' => esc_html__( 'Brella data', 'cryptocon-brella' ), 'tab' => 'content' ];
		$this->control_groups['grid']     = [ 'title' => esc_html__( 'Time grid', 'cryptocon-brella' ), 'tab' => 'content' ];
		$this->control_groups['tracks']   = [ 'title' => esc_html__( 'Tracks (theatres)', 'cryptocon-brella' ), 'tab' => 'content' ];
		$this->control_groups['bar']      = [ 'title' => esc_html__( 'Top bar (days, filters, switch)', 'cryptocon-brella' ), 'tab' => 'content' ];
		$this->control_groups['listview'] = [ 'title' => esc_html__( 'List view', 'cryptocon-brella' ), 'tab' => 'content' ];
		$this->control_groups['filters']  = [ 'title' => esc_html__( 'Filters', 'cryptocon-brella' ), 'tab' => 'content' ];
		$this->control_groups['cards']    = [ 'title' => esc_html__( 'Session cards', 'cryptocon-brella' ), 'tab' => 'content' ];
		$this->control_groups['popup']    = [ 'title' => esc_html__( 'Details popup', 'cryptocon-brella' ), 'tab' => 'content' ];
		$this->control_groups['colours']  = [ 'title' => esc_html__( 'Colours', 'cryptocon-brella' ), 'tab' => 'content' ];
		$this->control_groups['type']     = [ 'title' => esc_html__( 'Typography', 'cryptocon-brella' ), 'tab' => 'content' ];
	}

	public function set_controls() {

		/* ----- Source ----- */

		$this->controls['connection'] = [
			'tab'     => 'content',
			'group'   => 'source',
			'type'    => 'info',
			'content' => self::connection_info(),
		];

		$this->controls['group_by'] = [
			'tab'         => 'content',
			'group'       => 'source',
			'label'       => esc_html__( 'Columns', 'cryptocon-brella' ),
			'type'        => 'select',
			'options'     => [
				'auto'     => esc_html__( 'Auto (track, else location)', 'cryptocon-brella' ),
				'track'    => esc_html__( 'Brella track', 'cryptocon-brella' ),
				'location' => esc_html__( 'Location / room', 'cryptocon-brella' ),
				'tag'      => esc_html__( 'First tag', 'cryptocon-brella' ),
			],
			'default'     => 'auto',
			'description' => esc_html__( 'What each column (theatre) is built from.', 'cryptocon-brella' ),
		];

		$this->controls['tracks'] = [
			'tab'         => 'content',
			'group'       => 'source',
			'label'       => esc_html__( 'Tracks to show (optional)', 'cryptocon-brella' ),
			'type'        => 'text',
			'placeholder' => 'Main Stage|Hall A|Hall B',
			'description' => esc_html__( 'Pipe separated track names or IDs. Sets column order too. Leave blank for all tracks in Brella order.', 'cryptocon-brella' ),
		];

		$this->controls['include_networking'] = [
			'tab'   => 'content',
			'group' => 'source',
			'label' => esc_html__( 'Include 1:1 networking slots', 'cryptocon-brella' ),
			'type'  => 'checkbox',
		];

		$this->controls['hide_empty_tracks'] = [
			'tab'     => 'content',
			'group'   => 'source',
			'label'   => esc_html__( 'Hide tracks with no sessions that day', 'cryptocon-brella' ),
			'type'    => 'checkbox',
			'default' => true,
		];

		/* ----- Grid ----- */

		$this->controls['step'] = [
			'tab'         => 'content',
			'group'       => 'grid',
			'label'       => esc_html__( 'Row step (minutes)', 'cryptocon-brella' ),
			'type'        => 'select',
			'options'     => [ '5' => '5', '10' => '10', '15' => '15', '30' => '30' ],
			'default'     => '5',
			'inline'      => true,
			'description' => esc_html__( 'Smallest time unit. Match your shortest session.', 'cryptocon-brella' ),
		];

		$this->controls['label_interval'] = [
			'tab'     => 'content',
			'group'   => 'grid',
			'label'   => esc_html__( 'Time label every (minutes)', 'cryptocon-brella' ),
			'type'    => 'select',
			'options' => [ '15' => '15', '30' => '30', '60' => '60' ],
			'default' => '30',
			'inline'  => true,
		];

		$this->controls['slot_height'] = [
			'tab'     => 'content',
			'group'   => 'grid',
			'label'   => esc_html__( 'Height per step', 'cryptocon-brella' ),
			'type'    => 'number',
			'units'   => true,
			'css'     => [ [ 'property' => '--ba-slot-h' ] ],
			'placeholder' => '1.25rem',
		];

		$this->controls['time_w'] = [
			'tab'         => 'content',
			'group'       => 'grid',
			'label'       => esc_html__( 'Time column width', 'cryptocon-brella' ),
			'type'        => 'number',
			'units'       => true,
			'css'         => [ [ 'property' => '--ba-time-w' ] ],
			'placeholder' => '5.5rem',
		];

		$this->controls['height'] = [
			'tab'         => 'content',
			'group'       => 'grid',
			'label'       => esc_html__( 'Agenda height', 'cryptocon-brella' ),
			'type'        => 'number',
			'units'       => true,
			'css'         => [ [ 'property' => '--ba-h' ] ],
			'placeholder' => 'auto',
			'description' => esc_html__( 'Fixed height for the grid (e.g. 80vh or 900px). The grid scrolls inside it and track headers stay pinned. Leave blank to show the full day. Ignored in the phone list view.', 'cryptocon-brella' ),
		];

		$this->controls['max_h'] = [
			'tab'         => 'content',
			'group'       => 'grid',
			'label'       => esc_html__( 'Max height', 'cryptocon-brella' ),
			'type'        => 'number',
			'units'       => true,
			'css'         => [ [ 'property' => '--ba-max-h' ] ],
			'placeholder' => 'none',
			'description' => esc_html__( 'Grows with the day up to this height, then scrolls inside.', 'cryptocon-brella' ),
		];

		// Features added after the first release are opt-out ("Turn off ...") so agenda
		// elements already on a page get them without being re-saved. Bricks drops
		// unticked checkboxes, so an opt-in default would read as off on old elements.
		$this->controls['no_hscroll'] = [
			'tab'         => 'content',
			'group'       => 'grid',
			'label'       => esc_html__( 'Turn off horizontal scroll', 'cryptocon-brella' ),
			'type'        => 'checkbox',
			'description' => esc_html__( 'By default tracks keep their min width and the grid scrolls sideways. Tick to make tracks shrink to fit the container instead.', 'cryptocon-brella' ),
		];

		$this->controls['freeze'] = [
			'tab'         => 'content',
			'group'       => 'grid',
			'label'       => esc_html__( 'Freeze', 'cryptocon-brella' ),
			'type'        => 'select',
			'options'     => [
				'both'    => esc_html__( 'Time column and theatre headers', 'cryptocon-brella' ),
				'time'    => esc_html__( 'Time column only', 'cryptocon-brella' ),
				'headers' => esc_html__( 'Theatre headers only', 'cryptocon-brella' ),
				'none'    => esc_html__( 'Nothing', 'cryptocon-brella' ),
			],
			'placeholder' => esc_html__( 'Time column and theatre headers', 'cryptocon-brella' ),
			'description' => esc_html__( 'The time column stays put while you scroll sideways; the theatre headers stay at the top while you scroll down, whether the page scrolls or the agenda has its own height.', 'cryptocon-brella' ),
		];

		$this->controls['freeze_offset'] = [
			'tab'         => 'content',
			'group'       => 'grid',
			'label'       => esc_html__( 'Frozen headers offset (px)', 'cryptocon-brella' ),
			'type'        => 'number',
			'placeholder' => esc_html__( 'auto', 'cryptocon-brella' ),
			'description' => esc_html__( 'Gap above the pinned headers, e.g. for a sticky site header. Leave blank to detect a sticky Bricks header automatically.', 'cryptocon-brella' ),
		];

		$this->controls['breakout'] = [
			'tab'         => 'content',
			'group'       => 'grid',
			'label'       => esc_html__( 'Break out of container (right)', 'cryptocon-brella' ),
			'type'        => 'checkbox',
			'description' => esc_html__( 'The grid runs past its container to the right edge of the window. Only above the tablet breakpoint; the day tabs and filters stay in the container.', 'cryptocon-brella' ),
		];

		$this->controls['breakout_min'] = [
			'tab'         => 'content',
			'group'       => 'grid',
			'label'       => esc_html__( 'Break out above (px)', 'cryptocon-brella' ),
			'type'        => 'number',
			'placeholder' => (string) self::tablet_breakpoint(),
			'description' => esc_html__( 'Defaults to your Bricks tablet breakpoint.', 'cryptocon-brella' ),
			'required'    => [ 'breakout', '=', true ],
		];

		$this->controls['time_format'] = [
			'tab'         => 'content',
			'group'       => 'grid',
			'label'       => esc_html__( 'Time format', 'cryptocon-brella' ),
			'type'        => 'text',
			'placeholder' => 'g:i a',
			'inline'      => true,
		];

		$this->controls['day_format'] = [
			'tab'         => 'content',
			'group'       => 'grid',
			'label'       => esc_html__( 'Day tab format', 'cryptocon-brella' ),
			'type'        => 'text',
			'placeholder' => 'D j M',
			'inline'      => true,
		];

		$this->controls['show_timezone'] = [
			'tab'     => 'content',
			'group'   => 'grid',
			'label'   => esc_html__( 'Show timezone in corner', 'cryptocon-brella' ),
			'type'    => 'checkbox',
			'default' => true,
		];

		$this->controls['default_view'] = [
			'tab'         => 'content',
			'group'       => 'grid',
			'label'       => esc_html__( 'Opens in', 'cryptocon-brella' ),
			'type'        => 'select',
			'options'     => [
				'calendar' => esc_html__( 'Calendar view', 'cryptocon-brella' ),
				'list'     => esc_html__( 'List view', 'cryptocon-brella' ),
			],
			'placeholder' => esc_html__( 'Calendar view', 'cryptocon-brella' ),
			'description' => esc_html__( 'Visitors can switch with the Calendar / List buttons; their choice is remembered in their browser.', 'cryptocon-brella' ),
		];

		$this->controls['hide_view_toggle'] = [
			'tab'   => 'content',
			'group' => 'grid',
			'label' => esc_html__( 'Hide Calendar / List switch', 'cryptocon-brella' ),
			'type'  => 'checkbox',
		];

		$this->controls['mobile'] = [
			'tab'     => 'content',
			'group'   => 'grid',
			'label'   => esc_html__( 'Small screens', 'cryptocon-brella' ),
			'type'    => 'select',
			'options' => [
				'list'   => esc_html__( 'Chronological list', 'cryptocon-brella' ),
				'scroll' => esc_html__( 'Keep grid (swipe sideways)', 'cryptocon-brella' ),
			],
			'default' => 'list',
			'inline'  => true,
		];

		$this->controls['breakpoint'] = [
			'tab'         => 'content',
			'group'       => 'grid',
			'label'       => esc_html__( 'List below width (px)', 'cryptocon-brella' ),
			'type'        => 'number',
			'default'     => 768,
			'required'    => [ 'mobile', '=', 'list' ],
			'description' => esc_html__( 'Measured on the element, not the viewport.', 'cryptocon-brella' ),
		];

		/* ----- Tracks ----- */

		$this->controls['track_min'] = [
			'tab'         => 'content',
			'group'       => 'tracks',
			'label'       => esc_html__( 'Track width (all tracks)', 'cryptocon-brella' ),
			'type'        => 'number',
			'units'       => true,
			'css'         => [ [ 'property' => '--ba-track-min' ] ],
			'placeholder' => '14rem',
			'description' => esc_html__( 'Width of every track column. Columns still stretch to fill spare space unless Fixed width is ticked. Per-track settings below override this for individual tracks.', 'cryptocon-brella' ),
		];

		$this->controls['track_fixed'] = [
			'tab'         => 'content',
			'group'       => 'tracks',
			'label'       => esc_html__( 'Fixed width (all tracks)', 'cryptocon-brella' ),
			'type'        => 'checkbox',
			'description' => esc_html__( 'Every column is exactly the width above and never stretches. Ignored when horizontal scroll is turned off.', 'cryptocon-brella' ),
		];

		$this->controls['hide_track_sponsors'] = [
			'tab'         => 'content',
			'group'       => 'tracks',
			'label'       => esc_html__( 'Hide sponsor logos in track headers', 'cryptocon-brella' ),
			'type'        => 'checkbox',
			'description' => esc_html__( 'Sponsored tracks show their sponsor\'s logo from Brella in the header. Hide it here for every track, or per track below.', 'cryptocon-brella' ),
		];

		$this->controls['sponsor_position'] = [
			'tab'         => 'content',
			'group'       => 'tracks',
			'label'       => esc_html__( 'Sponsor logo position', 'cryptocon-brella' ),
			'type'        => 'select',
			'options'     => [
				'above'  => esc_html__( 'Above the track name', 'cryptocon-brella' ),
				'beside' => esc_html__( 'Beside the track name', 'cryptocon-brella' ),
			],
			'placeholder' => esc_html__( 'Above the track name', 'cryptocon-brella' ),
		];

		$this->controls['sponsor_logo_h'] = [
			'tab'         => 'content',
			'group'       => 'tracks',
			'label'       => esc_html__( 'Sponsor logo height', 'cryptocon-brella' ),
			'type'        => 'number',
			'units'       => true,
			'css'         => [ [ 'property' => '--ba-sponsor-h' ] ],
			'placeholder' => '1.75rem',
		];

		$this->controls['track_settings'] = [
			'tab'           => 'content',
			'group'         => 'tracks',
			'label'         => esc_html__( 'Per-track settings', 'cryptocon-brella' ),
			'type'          => 'repeater',
			'titleProperty' => 'track',
			'placeholder'   => esc_html__( 'Track', 'cryptocon-brella' ),
			'description'   => esc_html__( 'Optional. Add a row only for a track (theatre) that should differ from the settings above. Type the track name as it appears in the agenda header.', 'cryptocon-brella' ),
			'fields'        => [
				'track'     => [
					'label'       => esc_html__( 'Track name', 'cryptocon-brella' ),
					'type'        => 'text',
					'placeholder' => 'Main Stage',
				],
				'width'     => [
					'label'       => esc_html__( 'Width', 'cryptocon-brella' ),
					'type'        => 'number',
					'units'       => true,
					'placeholder' => '14rem',
					'description' => esc_html__( 'Overrides Track width (all tracks) for this track. The column still grows to fill spare space unless Fixed width is ticked.', 'cryptocon-brella' ),
				],
				'fixed'     => [
					'label' => esc_html__( 'Fixed width', 'cryptocon-brella' ),
					'type'  => 'checkbox',
				],
				'hide_logo' => [
					'label' => esc_html__( 'Hide sponsor logo', 'cryptocon-brella' ),
					'type'  => 'checkbox',
				],
				'logo'      => [
					'label'       => esc_html__( 'Sponsor logo (override)', 'cryptocon-brella' ),
					'type'        => 'image',
					'description' => esc_html__( 'Optional. Replaces the logo from Brella, or adds one if Brella has none.', 'cryptocon-brella' ),
				],
				'link'      => [
					'label'       => esc_html__( 'Sponsor link', 'cryptocon-brella' ),
					'type'        => 'text',
					'placeholder' => 'https://',
					'description' => esc_html__( 'Optional. Defaults to the sponsor\'s website in Brella.', 'cryptocon-brella' ),
				],
			],
		];

		/* ----- Filters ----- */

		$this->controls['hide_filters'] = [
			'tab'         => 'content',
			'group'       => 'filters',
			'label'       => esc_html__( 'Hide all filters', 'cryptocon-brella' ),
			'type'        => 'checkbox',
			'description' => esc_html__( 'Filters show on the right of the day tabs. A filter also hides itself when Brella has nothing to filter by (e.g. no tags).', 'cryptocon-brella' ),
		];

		$filter_toggles = [
			'hide_filter_track'   => 'Hide theatre (track) filter',
			'hide_filter_speaker' => 'Hide speaker filter',
			'hide_filter_tag'     => 'Hide tags filter',
			'hide_filter_type'    => 'Hide session type filter',
		];
		foreach ( $filter_toggles as $key => $label ) {
			$this->controls[ $key ] = [
				'tab'      => 'content',
				'group'    => 'filters',
				'label'    => $label,
				'type'     => 'checkbox',
				'required' => [ 'hide_filters', '!=', true ],
			];
		}

		$this->controls['track_label'] = [
			'tab'         => 'content',
			'group'       => 'filters',
			'label'       => esc_html__( 'Word for a track', 'cryptocon-brella' ),
			'type'        => 'text',
			'placeholder' => 'Theatre',
			'inline'      => true,
			'description' => esc_html__( 'Shown as "All theatres" in the dropdown.', 'cryptocon-brella' ),
			'required'    => [ 'hide_filters', '!=', true ],
		];

		/* ----- Cards ----- */

		$this->controls['show_subtitle'] = [
			'tab'     => 'content',
			'group'   => 'cards',
			'label'   => esc_html__( 'Subtitle (session type)', 'cryptocon-brella' ),
			'type'    => 'checkbox',
			'default' => true,
		];

		$this->controls['show_location'] = [
			'tab'     => 'content',
			'group'   => 'cards',
			'label'   => esc_html__( 'Location', 'cryptocon-brella' ),
			'type'    => 'checkbox',
			'default' => true,
		];

		$this->controls['show_speakers'] = [
			'tab'     => 'content',
			'group'   => 'cards',
			'label'   => esc_html__( 'Speakers', 'cryptocon-brella' ),
			'type'    => 'checkbox',
			'default' => true,
		];

		$this->controls['hide_avatars'] = [
			'tab'         => 'content',
			'group'       => 'cards',
			'label'       => esc_html__( 'Hide speaker avatars', 'cryptocon-brella' ),
			'type'        => 'checkbox',
			'description' => esc_html__( 'Avatars use the photo from each speaker\'s Brella profile, or their initials if none is uploaded.', 'cryptocon-brella' ),
		];

		$this->controls['max_avatars'] = [
			'tab'      => 'content',
			'group'    => 'cards',
			'label'    => esc_html__( 'Max avatars per card', 'cryptocon-brella' ),
			'type'     => 'number',
			'min'      => 1,
			'max'      => 12,
			'default'  => 3,
			'required' => [ 'hide_avatars', '!=', true ],
		];

		$this->controls['avatar_size'] = [
			'tab'         => 'content',
			'group'       => 'cards',
			'label'       => esc_html__( 'Avatar size', 'cryptocon-brella' ),
			'type'        => 'number',
			'units'       => true,
			'css'         => [ [ 'property' => '--ba-avatar-size' ] ],
			'placeholder' => '1.35rem',
			'required'    => [ 'hide_avatars', '!=', true ],
		];

		$this->controls['show_excerpt'] = [
			'tab'   => 'content',
			'group' => 'cards',
			'label' => esc_html__( 'Description on card', 'cryptocon-brella' ),
			'type'  => 'checkbox',
		];

		$this->controls['details'] = [
			'tab'     => 'content',
			'group'   => 'cards',
			'label'   => esc_html__( 'Click a session', 'cryptocon-brella' ),
			'type'    => 'select',
			'options' => [
				'modal' => esc_html__( 'Open details popup', 'cryptocon-brella' ),
				'none'  => esc_html__( 'Nothing', 'cryptocon-brella' ),
			],
			'default' => 'modal',
			'inline'  => true,
		];

		$this->controls['heading_tag'] = [
			'tab'     => 'content',
			'group'   => 'cards',
			'label'   => esc_html__( 'Session title tag', 'cryptocon-brella' ),
			'type'    => 'select',
			'options' => [ 'h2' => 'h2', 'h3' => 'h3', 'h4' => 'h4', 'p' => 'p' ],
			'default' => 'h3',
			'inline'  => true,
		];

		$this->controls['radius'] = [
			'tab'         => 'content',
			'group'       => 'cards',
			'label'       => esc_html__( 'Corner radius', 'cryptocon-brella' ),
			'type'        => 'number',
			'units'       => true,
			'css'         => [ [ 'property' => '--ba-radius' ] ],
			'placeholder' => '10px',
		];

		$this->controls['card_inset'] = [
			'tab'         => 'content',
			'group'       => 'cards',
			'label'       => esc_html__( 'Card spacing', 'cryptocon-brella' ),
			'type'        => 'number',
			'units'       => true,
			'css'         => [ [ 'property' => '--ba-card-inset' ] ],
			'placeholder' => '3px',
		];

		/* ----- Details popup ----- */

		$popup_sizes = [
			'popup_width'         => [ 'Popup width', '--ba-dialog-w', '40rem', '' ],
			'popup_padding'       => [ 'Popup padding', '--ba-dialog-pad', '1.75rem', '' ],
			'popup_title_size'    => [ 'Session title size', '--ba-dialog-title-size', '1.5rem', 'Or use Session title under Popup typography below for full control.' ],
			'speaker_photo_size'  => [ 'Speaker photo size', '--ba-speaker-photo', '48px', 'Initials scale with it when a speaker has no photo.' ],
			'speaker_photo_gap'   => [ 'Gap between photo and name', '--ba-speaker-gap', '0.75rem', '' ],
		];
		foreach ( $popup_sizes as $key => $c ) {
			$this->controls[ $key ] = [
				'tab'         => 'content',
				'group'       => 'popup',
				'label'       => $c[0],
				'type'        => 'number',
				'units'       => true,
				'css'         => [ [ 'property' => $c[1] ] ],
				'placeholder' => $c[2],
			] + ( $c[3] ? [ 'description' => $c[3] ] : [] );
		}

		$this->controls['speaker_photo_shape'] = [
			'tab'         => 'content',
			'group'       => 'popup',
			'label'       => esc_html__( 'Speaker photo shape', 'cryptocon-brella' ),
			'type'        => 'select',
			'options'     => [
				'50%'   => esc_html__( 'Circle', 'cryptocon-brella' ),
				'0.5rem' => esc_html__( 'Rounded square', 'cryptocon-brella' ),
				'0'     => esc_html__( 'Square', 'cryptocon-brella' ),
			],
			'css'         => [ [ 'property' => '--ba-speaker-radius' ] ],
			'placeholder' => esc_html__( 'Circle', 'cryptocon-brella' ),
			'inline'      => true,
		];

		$this->controls['popup_bg'] = [
			'tab'   => 'content',
			'group' => 'popup',
			'label' => esc_html__( 'Popup background', 'cryptocon-brella' ),
			'type'  => 'color',
			'css'   => [ [ 'property' => '--ba-dialog-bg' ] ],
		];

		$this->controls['popup_type_sep'] = [
			'tab'   => 'content',
			'group' => 'popup',
			'label' => esc_html__( 'Popup typography', 'cryptocon-brella' ),
			'type'  => 'separator',
		];

		$popup_type = [
			'type_popup_meta'     => [ 'Track, time and location', '.ba-detail__meta' ],
			'type_popup_subtitle' => [ 'Session type', '.ba-detail__subtitle' ],
			'type_popup_title'    => [ 'Session title', '.ba-dialog .ba-detail__title' ],
			'type_popup_content'  => [ 'Description', '.ba-detail__content' ],
			'type_speaker_name'   => [ 'Speaker name', '.ba-speaker__text strong' ],
			'type_speaker_role'   => [ 'Speaker role (e.g. Moderator)', '.ba-speaker__text em' ],
			'type_speaker_job'    => [ 'Speaker job title and company', '.ba-speaker__text span' ],
			'type_popup_tags'     => [ 'Tags', '.ba-tag' ],
		];
		foreach ( $popup_type as $key => $t ) {
			$this->controls[ $key ] = [
				'tab'   => 'content',
				'group' => 'popup',
				'label' => $t[0],
				'type'  => 'typography',
				'css'   => [ [ 'property' => 'font', 'selector' => $t[1] ] ],
			];
		}

		/* ----- Session cards: spacing, border, avatars, hover, Live ----- */

		$this->add_size_controls( 'cards', [
			'card_pad_y'          => [ 'Card padding (top and bottom)', '--ba-card-pad-y', '0.45rem' ],
			'card_pad_x'          => [ 'Card padding (sides)', '--ba-card-pad-x', '0.6rem' ],
			'card_border'         => [ 'Coloured left border width', '--ba-card-border', '3px', 'Use 0 to remove it.' ],
			'avatar_size_compact' => [ 'Avatar size on short cards (15 to 20 min)', '--ba-avatar-size-compact', '1.2rem' ],
			'avatar_size_short'   => [ 'Avatar size on one-line cards (10 min or less)', '--ba-avatar-size-short', '1.1rem' ],
		] );

		$this->controls['card_hover_shadow'] = [
			'tab'   => 'content',
			'group' => 'cards',
			'label' => esc_html__( 'Card shadow on hover', 'cryptocon-brella' ),
			'type'  => 'box-shadow',
			'css'   => [ [ 'property' => 'box-shadow', 'selector' => '.ba-grid .ba-session:hover' ] ],
		];

		$this->add_separator( 'cards', 'live_sep', esc_html__( 'Live now', 'cryptocon-brella' ) );

		$this->controls['live_label'] = [
			'tab'         => 'content',
			'group'       => 'cards',
			'label'       => esc_html__( 'Live badge text', 'cryptocon-brella' ),
			'type'        => 'text',
			'placeholder' => 'Live',
			'inline'      => true,
		];

		$this->controls['hide_live'] = [
			'tab'   => 'content',
			'group' => 'cards',
			'label' => esc_html__( 'Hide the Live badge', 'cryptocon-brella' ),
			'type'  => 'checkbox',
		];

		$this->controls['hide_live_ring'] = [
			'tab'   => 'content',
			'group' => 'cards',
			'label' => esc_html__( 'Hide the outline on live sessions', 'cryptocon-brella' ),
			'type'  => 'checkbox',
		];

		$this->controls['live_bg'] = [
			'tab'         => 'content',
			'group'       => 'cards',
			'label'       => esc_html__( 'Live badge background', 'cryptocon-brella' ),
			'type'        => 'color',
			'css'         => [ [ 'property' => '--ba-live-bg' ] ],
			'description' => esc_html__( 'Leave empty to use each session\'s track colour.', 'cryptocon-brella' ),
		];

		$this->controls['live_fg'] = [
			'tab'   => 'content',
			'group' => 'cards',
			'label' => esc_html__( 'Live badge text colour', 'cryptocon-brella' ),
			'type'  => 'color',
			'css'   => [ [ 'property' => '--ba-live-fg' ] ],
		];

		$this->add_size_controls( 'cards', [
			'live_ring' => [ 'Live outline thickness', '--ba-live-ring', '2px' ],
		] );

		/* ----- Time grid: columns, headers, time labels, lines ----- */

		$this->add_separator( 'grid', 'grid_look_sep', esc_html__( 'Columns, headers and lines', 'cryptocon-brella' ) );

		$this->add_size_controls( 'grid', [
			'col_gap'         => [ 'Gap between track columns', '--ba-gap', '0px' ],
			'head_pad_top'    => [ 'Track header padding (top)', '--ba-head-pad-top', '0.9rem' ],
			'head_pad_bottom' => [ 'Track header padding (bottom)', '--ba-head-pad-bottom', '0.7rem' ],
			'head_pad_x'      => [ 'Track header padding (sides)', '--ba-head-pad-x', '0.75rem' ],
			'head_line'       => [ 'Track header underline thickness', '--ba-head-line', '3px', 'The coloured line under each track name. Use 0 to remove it.' ],
			'time_pad_top'    => [ 'Time label padding (top)', '--ba-time-pad-top', '0.2rem' ],
			'time_pad_x'      => [ 'Time label padding (sides)', '--ba-time-pad-x', '0.75rem' ],
			'line_width'      => [ 'Grid line thickness', '--ba-line-width', '1px' ],
		] );

		$line_styles = [
			'solid'  => esc_html__( 'Solid', 'cryptocon-brella' ),
			'dashed' => esc_html__( 'Dashed', 'cryptocon-brella' ),
			'dotted' => esc_html__( 'Dotted', 'cryptocon-brella' ),
			'none'   => esc_html__( 'None', 'cryptocon-brella' ),
		];
		$this->controls['line_style'] = [
			'tab'         => 'content',
			'group'       => 'grid',
			'label'       => esc_html__( 'Half-hour lines', 'cryptocon-brella' ),
			'type'        => 'select',
			'options'     => $line_styles,
			'css'         => [ [ 'property' => '--ba-line-style' ] ],
			'placeholder' => esc_html__( 'Dashed', 'cryptocon-brella' ),
			'inline'      => true,
		];
		$this->controls['hour_line_style'] = [
			'tab'         => 'content',
			'group'       => 'grid',
			'label'       => esc_html__( 'Hour lines', 'cryptocon-brella' ),
			'type'        => 'select',
			'options'     => $line_styles,
			'css'         => [ [ 'property' => '--ba-hour-line-style' ] ],
			'placeholder' => esc_html__( 'Solid', 'cryptocon-brella' ),
			'inline'      => true,
		];

		/* ----- Top bar ----- */

		$this->add_size_controls( 'bar', [
			'pill_radius'   => [ 'Button corner radius', '--ba-pill-radius', '999px', 'Day tabs, filter dropdowns, the Calendar / List switch and tags. 999px gives pill ends.' ],
			'pill_pad_y'    => [ 'Button padding (top and bottom)', '--ba-pill-pad-y', '0.5em' ],
			'pill_pad_x'    => [ 'Button padding (sides)', '--ba-pill-pad-x', '1.1em' ],
			'bar_item_gap'  => [ 'Gap between buttons', '--ba-bar-item-gap', '0.5rem' ],
			'bar_gap'       => [ 'Gap between the bar and the agenda', '--ba-bar-gap', '1rem' ],
		] );

		$this->controls['compact_bar'] = [
			'tab'         => 'content',
			'group'       => 'bar',
			'label'       => esc_html__( 'Switch shows icons only below (px)', 'cryptocon-brella' ),
			'type'        => 'number',
			'placeholder' => '1100',
			'description' => esc_html__( 'Below this agenda width the Calendar / List switch drops its text labels to save space. Use 0 to always show the labels.', 'cryptocon-brella' ),
		];

		/* ----- List view ----- */

		$this->add_size_controls( 'listview', [
			'list_time_w'     => [ 'Time column width', '--ba-list-time-w', '9.5rem' ],
			'list_pad_y'      => [ 'Row padding (top and bottom)', '--ba-list-pad-y', '0.8rem' ],
			'list_pad_x'      => [ 'Row padding (sides)', '--ba-list-pad-x', '1rem' ],
			'list_gap'        => [ 'Gap between rows', '--ba-list-gap', '0.5rem' ],
			'list_avatar'     => [ 'Avatar size', '--ba-list-avatar', '2rem' ],
			'list_title_size' => [ 'Session title size', '--ba-list-title-size', '1.05rem' ],
		] );

		/* ----- Details popup: frame, backdrop, close button, cover image ----- */

		$this->add_separator( 'popup', 'popup_frame_sep', esc_html__( 'Frame, backdrop and cover image', 'cryptocon-brella' ) );

		$this->add_size_controls( 'popup', [
			'popup_radius'   => [ 'Popup corner radius', '--ba-dialog-radius', '14px' ],
			'backdrop_blur'  => [ 'Backdrop blur', '--ba-backdrop-blur', '3px' ],
			'close_size'     => [ 'Close button size', '--ba-close-size', '2.25rem' ],
			'cover_h'        => [ 'Cover image height', '--ba-cover-h', 'auto', 'The session cover image from Brella, when there is one.' ],
			'cover_max_h'    => [ 'Cover image max height', '--ba-cover-max-h', 'none' ],
		] );

		$this->controls['backdrop_colour'] = [
			'tab'   => 'content',
			'group' => 'popup',
			'label' => esc_html__( 'Backdrop colour', 'cryptocon-brella' ),
			'type'  => 'color',
			'css'   => [ [ 'property' => '--ba-backdrop' ] ],
		];

		$this->controls['cover_fit'] = [
			'tab'         => 'content',
			'group'       => 'popup',
			'label'       => esc_html__( 'Cover image fit', 'cryptocon-brella' ),
			'type'        => 'select',
			'options'     => [
				'cover'   => esc_html__( 'Fill (crop)', 'cryptocon-brella' ),
				'contain' => esc_html__( 'Fit (no crop)', 'cryptocon-brella' ),
			],
			'css'         => [ [ 'property' => '--ba-cover-fit' ] ],
			'placeholder' => esc_html__( 'Fill (crop)', 'cryptocon-brella' ),
			'inline'      => true,
		];

		$this->controls['hide_cover'] = [
			'tab'   => 'content',
			'group' => 'popup',
			'label' => esc_html__( 'Hide the cover image', 'cryptocon-brella' ),
			'type'  => 'checkbox',
		];

		/* ----- Colours ----- */

		$this->controls['theme'] = [
			'tab'     => 'content',
			'group'   => 'colours',
			'label'   => esc_html__( 'Base theme', 'cryptocon-brella' ),
			'type'    => 'select',
			'options' => [ 'dark' => 'Dark', 'light' => 'Light' ],
			'default' => 'dark',
			'inline'  => true,
		];

		$colour_vars = [
			'--ba-bg'             => 'Background',
			'--ba-surface'        => 'Card base',
			'--ba-head-bg'        => 'Track header background',
			'--ba-text'           => 'Text',
			'--ba-muted'          => 'Muted text',
			'--ba-line'           => 'Grid lines',
			'--ba-line-strong'    => 'Hour lines',
			'--ba-accent-default' => 'Fallback accent',
			'--ba-c-magenta'      => 'Brella "magenta" tracks',
			'--ba-c-green'        => 'Brella "green" tracks',
			'--ba-c-blue'         => 'Brella "blue" tracks',
			'--ba-c-cyan'         => 'Brella "cyan" tracks',
			'--ba-c-yellow'       => 'Brella "yellow" tracks',
			'--ba-c-orange'       => 'Brella "orange" tracks',
			'--ba-c-red'          => 'Brella "red" tracks',
			'--ba-c-purple'       => 'Brella "purple" tracks',
			'--ba-c-pink'         => 'Brella "pink" tracks',
			'--ba-c-teal'         => 'Brella "teal" tracks',
			'--ba-c-violet'       => 'Brella "violet" tracks',
			'--ba-c-grey'         => 'Brella "grey" / "gray" tracks',
		];

		foreach ( $colour_vars as $var => $label ) {
			$css = [ [ 'property' => $var ] ];
			if ( '--ba-c-grey' === $var ) {
				$css[] = [ 'property' => '--ba-c-gray' ];
			}
			$this->controls[ 'colour' . str_replace( '-', '_', $var ) ] = [
				'tab'   => 'content',
				'group' => 'colours',
				'label' => $label,
				'type'  => 'color',
				'css'   => $css,
			];
		}

		$this->controls['extra_colours'] = [
			'tab'           => 'content',
			'group'         => 'colours',
			'label'         => esc_html__( 'Other Brella track colours', 'cryptocon-brella' ),
			'type'          => 'repeater',
			'titleProperty' => 'name',
			'placeholder'   => esc_html__( 'Colour', 'cryptocon-brella' ),
			'description'   => esc_html__( 'For any other colour name Brella gives a track. Without a row here, those tracks use the Fallback accent.', 'cryptocon-brella' ),
			'fields'        => [
				'name'   => [
					'label'       => esc_html__( 'Brella colour name', 'cryptocon-brella' ),
					'type'        => 'text',
					'placeholder' => 'lime',
				],
				'colour' => [
					'label' => esc_html__( 'Colour', 'cryptocon-brella' ),
					'type'  => 'color',
				],
			],
		];

		$this->controls['card_mix'] = [
			'tab'         => 'content',
			'group'       => 'colours',
			'label'       => esc_html__( 'Card tint strength', 'cryptocon-brella' ),
			'type'        => 'number',
			'units'       => true,
			'css'         => [ [ 'property' => '--ba-card-mix' ] ],
			'placeholder' => '22%',
		];

		/* ----- Typography ----- */

		$type_targets = [
			'type_track'    => [ 'Track headers', '.ba-track-head' ],
			'type_time'     => [ 'Time labels', '.ba-time' ],
			'type_title'    => [ 'Session title', '.ba-session__title' ],
			'type_subtitle' => [ 'Session subtitle', '.ba-session__subtitle' ],
			'type_meta'     => [ 'Session time, location, speakers', '.ba-session__time, .ba-session__location, .ba-session__speakers' ],
			'type_tabs'     => [ 'Day tabs', '.ba-tab' ],
			'type_filters'  => [ 'Filter dropdowns', '.ba-filter__select' ],
		];

		$this->add_size_controls( 'type', [
			'base_font_size' => [ 'Base text size', '--ba-font-size', '0.875rem', 'Everything in the agenda sizes from these three unless a typography setting below overrides it.' ],
			'card_title_size' => [ 'Session and track title size', '--ba-title-size', '0.95rem' ],
			'meta_size'      => [ 'Small text size', '--ba-meta-size', '0.75rem', 'Times, locations, speakers, labels.' ],
		] );

		foreach ( $type_targets as $key => $t ) {
			$this->controls[ $key ] = [
				'tab'   => 'content',
				'group' => 'type',
				'label' => $t[0],
				'type'  => 'typography',
				'css'   => [ [ 'property' => 'font', 'selector' => $t[1] ] ],
			];
		}
	}

	/**
	 * Width of the widest tablet breakpoint in this Bricks install
	 * (tablet landscape if one is set up, otherwise tablet portrait, default 991px).
	 */
	public static function tablet_breakpoint() {
		$width = 991;
		if ( class_exists( '\\Bricks\\Breakpoints' ) && method_exists( '\\Bricks\\Breakpoints', 'get_breakpoint_by' ) ) {
			foreach ( [ 'tablet_landscape', 'tablet_portrait' ] as $key ) {
				$bp = \Bricks\Breakpoints::get_breakpoint_by( 'key', $key );
				if ( ! empty( $bp['width'] ) ) {
					return (int) $bp['width'];
				}
			}
		}
		return $width;
	}

	/**
	 * Settings status and link, shown at the top of the element's Brella panel.
	 */
	private static function connection_info() {
		$url = esc_url( Agenda::settings_url() );

		if ( ! Settings::credentials_complete() ) {
			return sprintf(
				/* translators: %s: settings page URL */
				__( 'No Brella API key yet. <a href="%s" target="_blank" rel="noopener">Configure the key in W3 Brella Integration</a>, then click Refresh now there.', 'cryptocon-brella' ),
				$url
			);
		}

		$count = count( Cache::get_sessions() );
		$sync  = Cache::last_sync_human();

		return sprintf(
			/* translators: 1: session count, 2: last sync time, 3: settings URL */
			__( 'Connected to Brella: %1$d sessions cached, last sync %2$s. <a href="%3$s" target="_blank" rel="noopener">Manage key and cache in W3 Brella Integration</a>.', 'cryptocon-brella' ),
			$count,
			esc_html( $sync ? $sync : 'never' ),
			$url
		);
	}

	/**
	 * Add a set of number-with-units controls that each set one CSS custom property.
	 *
	 * @param string $group Control group.
	 * @param array  $items key => [ label, property, placeholder, description ].
	 */
	private function add_size_controls( $group, array $items ) {
		foreach ( $items as $key => $c ) {
			$this->controls[ $key ] = [
				'tab'         => 'content',
				'group'       => $group,
				'label'       => $c[0],
				'type'        => 'number',
				'units'       => true,
				'css'         => [ [ 'property' => $c[1] ] ],
				'placeholder' => $c[2],
			] + ( ! empty( $c[3] ) ? [ 'description' => $c[3] ] : [] );
		}
	}

	/**
	 * "Other Brella track colours" repeater into name => CSS colour.
	 *
	 * @param mixed $rows Repeater value.
	 * @return array<int,array{name:string,colour:string}>
	 */
	private static function colours_from( $rows ) {
		$out = [];
		foreach ( is_array( $rows ) ? $rows : [] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$c = $row['colour'] ?? '';
			if ( is_array( $c ) ) {
				$c = $c['raw'] ?? ( $c['rgb'] ?? ( $c['hex'] ?? '' ) );
			}
			$out[] = [ 'name' => (string) ( $row['name'] ?? '' ), 'colour' => (string) $c ];
		}
		return $out;
	}

	private function add_separator( $group, $key, $label ) {
		$this->controls[ $key ] = [
			'tab'   => 'content',
			'group' => $group,
			'label' => $label,
			'type'  => 'separator',
		];
	}

	/**
	 * Repeater rows into plain values (image control to URL).
	 *
	 * @param mixed $rows Repeater value.
	 * @return array<int,array<string,mixed>>
	 */
	private static function track_settings_from( $rows ) {
		$out = [];
		foreach ( is_array( $rows ) ? $rows : [] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$logo = '';
			if ( ! empty( $row['logo'] ) && is_array( $row['logo'] ) ) {
				if ( ! empty( $row['logo']['id'] ) && function_exists( 'wp_get_attachment_image_url' ) ) {
					$logo = (string) wp_get_attachment_image_url( (int) $row['logo']['id'], 'medium' );
				}
				if ( '' === $logo && ! empty( $row['logo']['url'] ) ) {
					$logo = (string) $row['logo']['url'];
				}
			}
			$out[] = [
				'track'     => (string) ( $row['track'] ?? '' ),
				'width'     => (string) ( $row['width'] ?? '' ),
				'fixed'     => ! empty( $row['fixed'] ),
				'logo'      => $logo,
				'link'      => (string) ( $row['link'] ?? '' ),
				'hide_logo' => ! empty( $row['hide_logo'] ),
			];
		}
		return $out;
	}

	public function render() {
		$s = $this->settings;

		if ( ! Settings::credentials_complete() ) {
			echo $this->render_element_placeholder( [ 'title' => esc_html__( 'Add your Brella API key under Settings > W3 Brella Integration.', 'cryptocon-brella' ) ] ); // phpcs:ignore
			return;
		}

		// Bricks omits unchecked checkboxes, so booleans are set explicitly here.
		$bool = function ( $key ) use ( $s ) {
			return ! empty( $s[ $key ] );
		};

		$o = Agenda_Renderer::parse(
			[
				'group_by'           => $s['group_by'] ?? 'auto',
				'step'               => $s['step'] ?? 5,
				'label_interval'     => $s['label_interval'] ?? 30,
				'time_format'        => $s['time_format'] ?? '',
				'day_format'         => $s['day_format'] ?? '',
				'tracks'             => $s['tracks'] ?? '',
				'include_networking' => $bool( 'include_networking' ),
				'hide_empty_tracks'  => $bool( 'hide_empty_tracks' ),
				'show_speakers'      => $bool( 'show_speakers' ),
				'show_avatars'       => ! $bool( 'hide_avatars' ),
				'max_avatars'        => $s['max_avatars'] ?? 3,
				'show_location'      => $bool( 'show_location' ),
				'show_subtitle'      => $bool( 'show_subtitle' ),
				'show_excerpt'       => $bool( 'show_excerpt' ),
				'show_timezone'      => $bool( 'show_timezone' ),
				'details'            => $s['details'] ?? 'modal',
				'mobile'             => $s['mobile'] ?? 'list',
				'breakpoint'         => $s['breakpoint'] ?? 768,
				'theme'              => $s['theme'] ?? 'dark',
				'heading_tag'        => $s['heading_tag'] ?? 'h3',
				'show_filters'       => ! $bool( 'hide_filters' ),
				'filters'            => implode( ',', array_filter( [
					$bool( 'hide_filter_track' ) ? '' : 'track',
					$bool( 'hide_filter_speaker' ) ? '' : 'speaker',
					$bool( 'hide_filter_tag' ) ? '' : 'tag',
					$bool( 'hide_filter_type' ) ? '' : 'type',
				] ) ),
				'track_label'        => $s['track_label'] ?? '',
				'hscroll'            => ! $bool( 'no_hscroll' ),
				'breakout'           => $bool( 'breakout' ),
				'freeze'             => $s['freeze'] ?? 'both',
				'default_view'       => $s['default_view'] ?? 'calendar',
				'view_toggle'        => ! $bool( 'hide_view_toggle' ),
				'show_track_sponsors' => ! $bool( 'hide_track_sponsors' ),
				'track_fixed'        => $bool( 'track_fixed' ),
				'live_label'         => $s['live_label'] ?? '',
				'hide_live'          => $bool( 'hide_live' ),
				'hide_live_ring'     => $bool( 'hide_live_ring' ),
				'hide_cover'         => $bool( 'hide_cover' ),
				'compact_bar'        => isset( $s['compact_bar'] ) && '' !== $s['compact_bar'] ? $s['compact_bar'] : 1100,
				'extra_colours'      => self::colours_from( $s['extra_colours'] ?? [] ),
				'sponsor_position'   => $s['sponsor_position'] ?? 'above',
				'track_settings'     => self::track_settings_from( $s['track_settings'] ?? [] ),
				'freeze_offset'      => $s['freeze_offset'] ?? '',
				'breakout_min'       => ! empty( $s['breakout_min'] ) ? $s['breakout_min'] : self::tablet_breakpoint(),
			]
		);

		$this->set_attribute( '_root', 'class', explode( ' ', Agenda_Renderer::root_classes( $o ) ) );
		foreach ( Agenda_Renderer::root_attributes( $o ) as $k => $v ) {
			$this->set_attribute( '_root', $k, $v );
		}
		$style = Agenda_Renderer::root_style( $o );
		if ( '' !== $style ) {
			$this->set_attribute( '_root', 'style', $style );
		}

		echo "<div {$this->render_attributes( '_root' )}>" . Agenda_Renderer::render( $o ) . '</div>'; // phpcs:ignore
	}
}

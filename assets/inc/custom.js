jQuery(document).ready(function($) {

$("#wa_wps_foo1").carouFredSel({
	circular: false,
	responsive: true,
	infinite: false,
	auto 	: false,
	prev	: {	
		button	: ".wa_wps_prev",
		key		: "left"
	},
	next	: { 
		button	: ".wa_wps_next",
		key		: "right"
	},
	pagination	: ".wa_wps_pagination",
		items: {
						
					//	height: '30%',	//	optionally resize item-height
						visible: {
							min: 1,
							max: 100000
						}
					}
});


});
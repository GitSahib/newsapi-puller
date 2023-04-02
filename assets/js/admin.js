jQuery(function($){
	var container = $(".newsapi-puller-settings-wrap");
	var dataService = NewsApiPuller.dataService; 
	
	$(container).on("change", "#api_json_textarea", function(){ 
		$(this).next(".error-hint").addClass("hidden");
		json_beautify($(this));
	});
	$(container).on("change", "#api_key,#news_ai_api_key,#country,#source", function(){ 
		$(this).next(".error-hint").addClass("hidden");
	});
	$(container).on("change", "#api_type", function(){ 
		container.find(".error-hint").addClass("hidden");
	});

	$(container).on("click", "#submit", function()
	{	
		var data = getData("api_key,news_ai_api_key,newsdata_io_api_key");
		var isValid = data.api_key && data.news_ai_api_key;
		if( !data.api_key) 
		{
			container.find("#api_key").next(".error-hint").removeClass("hidden");
		}
		if( !data.api_key) 
		{
			container.find("#api_key").next(".error-hint").removeClass("hidden");
		}
		if(!isValid)
		{
			return;
		}
		dataService.post("settings", data);
		return false;
	});
	$(container).on("click", "#pull-news", function()
	{		
		data = get_api_data_for_pull();
		if(!data.isValid)
		{
			return;
		} 
		dataService.get("news", data);
		return false;
	})
	$(container).on("click", "#schedule-news", function()
	{		
		data = get_api_data_for_schedule();
		if(!data.isValid)
		{
			return;
		} 
		dataService.post("schedule", data);
		return false;
	})
	$(container).on("click", "#unschedule-news", function()
	{		 
		var control = container.find("#api_type");
		var api_type = control.val();
		if(!api_type) {
			control.next(".error-hint").removeClass("hidden");
			return;
		}
		dataService.delete("schedule?api_type=" + api_type);
		return false;
	})
	$(container).on("click", ".nav-tab", function(){
		url = $(this).data('tab');
	}); 
	var self = this;

	function getData(key)
	{ 
		if(!key)
		{
			key = container.find(".body").hasClass("import") ? "api_json_textarea" : "api_key";
		}
		var keys = key.split(",");
		var data = {};
		for(var i = 0; i < keys.length; i++)
		{
			key = keys[i];
			var control = container.find("#" + key);
			var $v = control.val();			
			data[key] = $v;
		}
		return data;
	}

	function get_api_data_for_pull() {
		var data = getData("country,source,api_type");
		var isValid  = true;
		if(data.api_type == "1")
		{
			var isValid = (data.source || data.country);
			if( !(data.source || data.country ) )
			{
				container.find("#country,#source").next(".error-hint").removeClass("hidden");
			}
		} 
		else if(["2", "3"].indexOf(data.api_type) < 0)
		{
			container.find("#api_type").next(".error-hint").removeClass("hidden");
			isValid = false;
		}

		data.isValid = isValid;
		return data;
	}
	function get_api_data_for_schedule(){
		var data = getData("country,source,frequency,api_type");
		var isValid  = true;
		if(data.api_type == "1")
		{
			var isValid = (data.source || data.country) && data.frequency;
			if( !(data.source || data.country ) )
			{
				container.find("#country,#source").next(".error-hint").removeClass("hidden");
			}
			if( !data.frequency )
			{
				container.find("#frequency").next(".error-hint").removeClass("hidden");	
			}
		}
		else if(["2", "3"].indexOf(data.api_type) >= 0) 
		{
			var isValid =  data.frequency;
			if( !data.frequency )
			{
				container.find("#frequency").next(".error-hint").removeClass("hidden");	
			}
		}
		else 
		{
			container.find("#api_type").next(".error-hint").removeClass("hidden");
			isValid = false;
		}

		data.isValid = isValid;
		return data;
	}

	function json_beautify(el) {
	    try
	    {
	    	val = el.val().split("-{").join("{").split("-\"").join("\""),
	        obj = JSON.parse(val),
	        pretty = JSON.stringify(obj, undefined, 4);
	        el.val(pretty);
	        el.parents(".form-group").find(".error-json-hint").addClass("hidden");
	        el.data("is-valid-json", true);
	    }
	    catch(ex)
	    {
	    	el.parents(".form-group").find(".error-json-hint").removeClass("hidden");
	    	el.data("is-valid-json", false);
	    }
	};

});
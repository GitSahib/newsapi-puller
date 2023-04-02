jQuery(function($){
	NewsApiPuller.dataService = {

		get: function(url, data)
		{
			return request(url, data, 'GET');
		},
		post: function(url, data)
		{
			return request(url, data, 'POST');
		},
		put: function(url, data)
		{
			return request(url, data, 'PUT');
		},
		delete: function(url, data)
		{
			return request(url, data, 'DELETE');
		},
		head: function(url, data)
		{
			return request(url, data, 'HEAD');
		}

	}
	function request(url, data, method)
	{
		NewsApiPuller.notificationService.showLoader();
		return $.ajax({ 
			url: apiUrl(url), 
			method:method, 
			data: data, 
			beforeSend: function( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', NewsApiPuller.api.nonce );
			}
		}).done(function(data){
			NewsApiPuller.notificationService.handleResponse(data);
		}).error(function(data){
			if(data.status == 403)
			{
				NewsApiPuller.notificationService.handleResponse({
					status: 0,
					message: 'Please reload your page, your session seems to be expired.'
				});
			}
			else
			{
				NewsApiPuller.notificationService.handleResponse(data.responseText);
			}
		}).always(function(){
			NewsApiPuller.notificationService.hideLoader();
		});
	}
	function apiUrl(url){
		return NewsApiPuller.api.base + url;
	}

});
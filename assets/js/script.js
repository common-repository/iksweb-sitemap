/*
Plugin Name: XML Sitemap генератор
Plugin URI: https://plugin.iksweb.ru/wprdpress/
Author: Сергей Князев
*/
(function($){
	
	$("ul.adm-detail-tabs-block").on("click", "li:not(.active)", function() {
      $(this)
        .addClass("active")
        .siblings()
        .removeClass("active")
        .closest("div.tabs")
        .find("div.adm-detail-content-wrap")
        .removeClass("active")
        .eq($(this).index())
        .addClass("active");
        
        history.pushState(null, null, "#"+$(this).attr('data-id'));	
    });

    if(window.location.hash) {
    	
		var hash = $('ul.adm-detail-tabs-block li[data-id="'+window.location.hash.split('#')[1]+'"]');
		
		hash
		  .addClass("active")
		  .siblings()
		  .removeClass("active")
		  .closest("div.tabs")
		  .find("div.adm-detail-content-wrap")
		  .removeClass("active")
		  .eq(hash.index())
		  .addClass("active");
	}
	
    $('.AllRows').click(function(){
		var table=$(this).attr('data-table');
		console.log(table);
		if ($(this).is(':checked')){
			$(table+' input:checkbox').prop('checked', true);
		} else {
			$(table+' input:checkbox').prop('checked', false);
		}
	});
	
	// Массовое редактирование 
	(function($){
		$('.priority-set').change(function(){
			$('table.pages input').each(function() {
				$('#'+$(this).attr('data-id')+' .priority option[value='+$('.priority-set').val()+']').prop('selected', true);
			});
		});	
		$('.frequency-set').change(function(){
			$('table.pages input').each(function() {
				$('#'+$(this).attr('data-id')+' .frequency option[value='+$('.frequency-set').val()+']').prop('selected', true);
			});
		});	
		$('.priority-set-type').change(function(){
			$('table.types input').each(function() {
				$('#'+$(this).attr('data-id')+' .priority-type option[value='+$('.priority-set-type').val()+']').prop('selected', true);
			});
		});	
		$('.frequency-set-type').change(function(){
			$('table.types input').each(function() {
				$('#'+$(this).attr('data-id')+' .frequency-type option[value='+$('.frequency-set-type').val()+']').prop('selected', true);
			});
		});	
		
		$('.set-input[data-type]').click(function(){
			var type = $(this).attr('data-type');
			if ($(this).is(':checked')){
				$('table.pages input.'+type+':checkbox').prop('checked', true);
			} else {
				$('table.pages input.'+type+':checkbox').prop('checked', false);
			}
		});
		
		$('.set-select[data-type]').change(function(){
			var select	= $(this).attr('data-select');
			var type	= $(this).attr('data-type');
			var value	= $(this).val();
			
			$('table.pages select.'+select+'[data-type="'+type+'"] option[value='+value+']').prop('selected', true);
		});
		
	})(jQuery);

})(jQuery);
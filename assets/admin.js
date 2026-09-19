jQuery(function($){
  $(document).on('click','.pvr-media-select',function(e){
    e.preventDefault();
    const $btn=$(this);
    const $field=$btn.siblings('textarea').first();
    const frame=wp.media({
      title:'انتخاب ویدئوها',
      button:{text:'افزودن ویدئوها'},
      library:{type:'video'},
      multiple:true
    });
    frame.on('select',function(){
      const urls=[];
      frame.state().get('selection').each(function(att){
        const a=att.toJSON();
        if(a.url) urls.push(a.url);
      });
      const current=($field.val()||'').split(/\r?\n/).map(x=>x.trim()).filter(Boolean);
      $field.val([...new Set([...current,...urls])].join('\n')).trigger('change');
    });
    frame.open();
  });
});
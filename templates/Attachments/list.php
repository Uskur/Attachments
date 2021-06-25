<?php
$this->Html->script('/uskur/webpage_manager/bower_components/js-cookie/src/js.cookie.js',['block'=>'script']);
$this->Html->script('/uskur/remark_template/topbar/assets/js/BaseApp.js',['block'=>'script']);
$this->Html->script('/uskur/remark_template/topbar/assets/js/App/Media.js',['block'=>'script']);
$this->Html->css('/uskur/remark_template/topbar/assets/examples/css/apps/media.css',['block'=>'css']);
$this->Html->css('/uskur/webpage_manager/bower_components/blueimp-file-upload/css/jquery.fileupload.css',['block'=>'css']);
$this->Html->script('/uskur/remark_template/global/vendor/jquery-ui/jquery-ui.js',['block'=>'script']);
$this->Html->script('/uskur/webpage_manager/bower_components/blueimp-file-upload/js/jquery.fileupload.js',['block'=>'script']);

$this->Html->script('/uskur/remark_template/global/vendor/jstree/jstree.min.js',['block'=>'script']);
$this->Html->script('/uskur/remark_template/global/js/Plugin/jstree.js',['block'=>'script']);
$this->Html->css('/uskur/remark_template/global/vendor/jstree/jstree.min.css',['block'=>'css']);

$this->Html->script('/uskur/webpage_manager/bower_components/jquery-ui-sortable/jquery-ui-sortable.min.js',['block'=>'script']);

$pageTitle = __d('Uskur/Attachments','Attachments');
$this->assign('title', $pageTitle);
$this->Breadcrumbs->add($pageTitle, ['action' => 'index']);
$this->start('context-menu'); ?>
	<div id="folderButtons" class="btn-group" role="group" aria-label="Actions">
		
    
    <span class="btn btn-success btn-icon btn-outline btn-sm fileinput-button">
        <i class="icon fa-upload"></i>
        <span>Upload</span>
        <!-- The file input field used as target for the file upload widget -->
        <input id="fileupload" type="file" name="files[]" multiple>
    </span>
    
        
    </div>
<?php $this->end(); ?>
<div id="progress" class="progress progress-xs"><div class="progress-bar progress-bar-success"></div></div>
    <div id="files" class="files"></div>

      <!-- Media Content -->
      <div id="mediaContent" class="app-media page-content page-content-table" data-plugin="asSelectable">
        <!-- Media -->
        <div>
        	<table class="table">
        		<thead>
        			<tr>
        				<th style="width:40px;text-align:center;"><i class="fa fa-arrows-v fa-fw" aria-hidden="true"></i></th>
        				<th style="width:140px;"><?= __d('Uskur/WebpageManager','Preview')?></th>
        				<th><?= __d('Uskur/WebpageManager','Name')?></th>
        				<th class="text-center"><?= __d('Uskur/WebpageManager','Size')?></th>
        				<th class="text-center"><?= __d('Uskur/WebpageManager','Created')?></th>
        				<th><?= __d('Uskur/WebpageManager','Actions')?></th>
        			</tr>
        		</thead>
        		<tbody id="fileList" class="sortable"></tbody>
        	</table>
          
        </div>
      </div>

<script type="text/template" id="imageTemplate">
<tr id="fileId">
    <td class="handle" data-pos=""><i class="fa fa-bars fa-fw" aria-hidden="true"></i></td>
    <td><?php echo $this->Html->image(['plugin'=>'Uskur/Attachments','controller'=>'Attachments','action'=>'image','fileId','?'=>['w'=>120,'h'=>90]]); ?></td>
    <td>fileName<br><small>fileTitle</small></td>
    <td class="text-right">size</td>
    <td class="text-right">created</td>
    <td>
        <?php if(isset($this->request->query['CKEditorFuncNum'])): ?>
        <?php if(isset($this->request->query['filter']) && $this->request->query['filter'] == 'image'):?>
            <a href="javascript:void(0)" data-id="fileId" data-name="fileName" class="selectImage btn btn-icon btn-success btn-outline btn-sm" title="Select"><i class="icon fa-image" aria-hidden="true"></i></a>
        <?php else: ?>
            <a href="javascript:void(0)" data-id="fileId" data-name="fileName" class="selectFile btn btn-icon btn-success btn-outline btn-sm" title="Select"><i class="icon fa-chain" aria-hidden="true"></i></a>
        <?php endif; ?>
        <?php endif; ?>
        <?= $this->Html->iconButton('fa-download', ['plugin'=>'Uskur/Attachments','controller'=>'Attachments','action'=>'download','fileId','fileName'],['title'=>__d('Uskur/WebpageManager','Download')]) ?>
        <a data-id="fileId" href="javascript:void(0)" class="editFileAttribute btn btn-icon btn-primary btn-outline btn-sm"><i class="icon fa-pencil" aria-hidden="true"></i></a>
        <a data-id="fileId" href="javascript:void(0)" class="deleteFile btn btn-icon btn-danger btn-outline btn-sm"><i class="icon fa-trash" aria-hidden="true"></i></a>
    </td>
</tr> 
</script>

<script type="text/template" id="fileTemplate">
<tr id="fileId">
    <td class="handle" data-pos=""><i class="fa fa-bars fa-fw" aria-hidden="true"></i></td>
    <td><i class="icon fa-file" style="font-size:64px;"></i></td>
    <td>fileName<br><small>fileTitle</small></td>
    <td class="text-right">size</td>
    <td class="text-center">created</td>
    <td>
        <?php if(isset($this->request->query['CKEditorFuncNum'])): ?>
        <?php if(isset($this->request->query['filter']) && $this->request->query['filter'] == 'image'):?>
            <a href="javascript:void(0)" data-id="fileId" data-name="fileName" class="selectImage btn btn-icon btn-success btn-outline btn-sm" title="Select"><i class="icon fa-image" aria-hidden="true"></i></a>
        <?php else: ?>
            <a href="javascript:void(0)" data-id="fileId" data-name="fileName" class="selectFile btn btn-icon btn-success btn-outline btn-sm" title="Select"><i class="icon fa-chain" aria-hidden="true"></i></a>
        <?php endif; ?>
        <?php endif; ?>
        <?= $this->Html->iconButton('fa-download', ['plugin'=>'Uskur/Attachments','controller'=>'Attachments','action'=>'download','fileId','fileName'],['title'=>__d('Uskur/WebpageManager','Download')]) ?>
        <a data-id="fileId" href="javascript:void(0)" class="editFileAttribute btn btn-icon btn-primary btn-outline btn-sm"><i class="icon fa-pencil" aria-hidden="true"></i></a>
        <a data-id="fileId" href="javascript:void(0)" class="deleteFile btn btn-icon btn-danger btn-outline btn-sm"><i class="icon fa-trash" aria-hidden="true"></i></a>
    </td>
</tr> 
</script>
    
<?php $this->append('script'); ?>
<script type="text/javascript">
var activeFolder = null;
var ckeditor = <?= isset($this->request->query['CKEditorFuncNum'])?$this->request->query['CKEditorFuncNum']:'null' ?>;
function printFolders() {
	$('#folderList').jstree().settings.core.data = $.parseJSON(folders);
	$('#folderList').jstree().refresh();
	return;
	if($(folders).length === 0) {
		$('#folderList').html('<div class="alert alert-primary" role="alert"><?= __d('Uskur/WebpageManager','No folders exist.');?></div>');
	}
	else{
		$('#folderList').html('');
		$(folders).each(function(index,folder){
		$('#folderList').append('<a class="list-group-item folderLine" href="javascript:void(0)" id="'+folder.id+'"><i class="icon fa-folder" aria-hidden="true"></i>'+folder.name+'</a>');
		});
	}
}

function getFolder(id = null){
	$('.folderLine').removeClass('active');
	if(id == null) {
		printFiles();
	}
	else{
    	$.get('<?= $this->Url->build(['action'=>'list'])?>/'+id+'.json?filter=<?= $this->request->getQuery('filter')?>',function(data){
        	printFiles(data);
    	},'json');
	}
}

function renameFolder(){
	if(activeFolder != null) {
		$('#renameFolderModal').modal('show');
		$('#rename-id').val(activeFolder.id);
		$('#rename-name').val(activeFolder.name);
	}
}

function importFiles(){
	if(activeFolder != null) {
		$('#importFilesModal').modal('show');
		$('#import-id').val(activeFolder.id);
	}
}

function printFiles(data){
	$('#fileList').html('');
	if(data.attachments.length > 0) {
    	$(data.attachments).each(function(index,file){
    		printFile(file);
    	});
	}
	else{
		$('#fileList').append('<tr><td colspan="6" class="text-center"><?= __d('Uskur/WebpageManager','No files.') ?></td></tr>');
	}
}

function printFile(file){
	if(file.filetype.startsWith('image')){
		template = $.trim($('#imageTemplate').html());
	}
	else{
		template = $.trim($('#fileTemplate').html());
	}
	newItemHtml = template.replace(/fileName/g, file.filename)
	.replace(/fileTitle/g, file.details_array != null && file.details_array.hasOwnProperty('title')?file.details_array.title:'')
	.replace(/fileId/g, file.id)
	.replace(/created/g, file.readable_created)
	.replace(/size/g, file.readable_size);
	$('#fileList').append(newItemHtml);
}

function deleteFile(id){
	if(confirm("<?= __d('Uskur/WebpageManager',"Are you sure you want to delete this file?");?>")){
	  $.post('<?php echo $this->Url->build(['plugin'=>'Uskur/Attachments','controller'=>'Attachments','action'=>'delete']); ?>/'+id+'.json', {}, function( data ) {
			  $('#'+id).remove();
  	  },'json');
	}
}

function returnCkImage(id){
    url = '<?php echo $this->Url->build(['plugin'=>'Uskur/Attachments','controller'=>'Attachments','action'=>'image'],$this->request->query('full')); ?>/'+id;
	window.opener.CKEDITOR.tools.callFunction( ckeditor, url );
	window.close();
}

function returnCkFile(id, name){
    url = '<?php echo $this->Url->build(['plugin'=>'Uskur/Attachments','controller'=>'Attachments','action'=>'file'],$this->request->query('full')); ?>/'+id+'/'+name;
	window.opener.CKEDITOR.tools.callFunction( ckeditor, url );
	window.close();
}

$(function () { 
	$.ajaxSetup({
	    headers: {
	        'X-CSRF-TOKEN': "<?= $this->request->getParam('_csrfToken') ?>"
	    }
	});
	
	getFolder("<?= str_replace("/", "-", $model)."/$fk"?>");

    $( "#importFilesForm" ).submit(function( event ) {
    	  event.preventDefault();
    	  $.post( $( "#importFilesForm" ).attr('action')+'/'+activeFolder.id+'.json', $( "#importFilesForm" ).serialize(), function( data ) {
    		  folders = data['fileFolders'];
    		  printFolders();
    		  getFolder(activeFolder.id);
    		  $('#importFilesModal').modal('hide');
    	  },'json')
    	});
    $( "#folderList" ).on('click','.folderLine',function( event ) {
  	  event.preventDefault();
  	  getFolder(event.target.id);
  	});

    $( "#fileList" ).on('click','.deleteFile',function( event ) {
  	  event.preventDefault();
  	  deleteFile($(event.currentTarget).data('id'));
  });

    $( "#fileList" ).on('click','.selectFile',function( event ) {
    	  event.preventDefault();
    	  returnCkFile($(event.currentTarget).data('id'),$(event.currentTarget).data('name'));
    });

    $( "#fileList" ).on('click','.selectImage',function( event ) {
  	  event.preventDefault();
  	  returnCkImage($(event.currentTarget).data('id'));
  });

    $( "#fileList" ).on('click','.editFileAttribute',function( event ) {
  	 	event.preventDefault();
    	$.get('<?= $this->Url->build(['action'=>'fileAttribute'])?>/'+$(event.currentTarget).data('id')+'.json',function(data){
    		$('#fileAttributes').modal('show');
    		$('#attribute-id').val(data['attachment']['id']);
    		$('#attribute-name').html(data['attachment']['filename']);

        		if(data['attachment']['details_array'] !== null && data['attachment']['details_array']['title'] !== null) {
        			$('#attribute-title').val(data['attachment']['details_array']['title']);
        		}
        		else{
        			$('#attribute-title').val('');
        		}
    
        		if(data['attachment']['details_array'] !== null && data['attachment']['details_array']['description'] !== null) {
        			$('#attribute-description').val(data['attachment']['details_array']['description']);
        		}
        		else{
        			$('#attribute-description').val('');
        		}
    
        		if(data['attachment']['details_array'] !== null && data['attachment']['details_array']['link'] !== null) {
        			$('#attribute-link').val(data['attachment']['details_array']['link']);
        		}
        		else{
        			$('#attribute-link').val('');
        		}	
    	},'json');  
    });

    $( "#fileAttributeForm" ).submit(function( event ) {
    	  event.preventDefault();
    	  $.post( $( "#fileAttributeForm" ).attr('action')+'/'+$('#attribute-id').val()+'.json', $( "#fileAttributeForm" ).serialize(), function( data ) {
    		  $('#fileAttributes').modal('hide');
    	  },'json')
    	});

    $('#fileupload').fileupload({
        url: '',
        dataType: 'json',
        add: function(e, data) {
            data.url = '<?php echo $this->Url->build(['action'=>'add',str_replace("/","-", $model),$fk,'_ext'=>'json']);?>';
            data.submit();
        },
        done: function (e, data) {
        	getFolder(activeFolder.id);
            $.each(data.result.files, function (index, file) {
                $('<p/>').text(file.name).appendTo('#files');
            });
        },
        stop: function (e) {
        	getFolder(activeFolder.id);
        },
        progressall: function (e, data) {
            var progress = parseInt(data.loaded / data.total * 100, 10);
            $('#progress .progress-bar').css(
                'width',
                progress + '%'
            );
        }
    }).prop('disabled', !$.support.fileInput)
    .parent().addClass($.support.fileInput ? undefined : 'disabled');

    
    
});





$(function () { 


	$( "tbody.sortable" ).sortable({ 
		handle: '.handle',
		update: function(event, ui) {
			  ui.item.find('i.fa-bars').removeClass('fa-bars').addClass('fa-spinner fa-spin');
		      position = ui.item.index()+1;
		      url = '<?= $this->Url->build(['plugin'=>'Uskur/Attachments','controller'=>'Attachments','action'=>'updatePosition']); ?>';
		        $.ajax({
		            type: 'GET',
		            url: url+'/'+ui.item.attr('id')+'/'+position
		        }).done(function() {
		        	ui.item.find('i.fa-spinner').removeClass('fa-spinner fa-spin').addClass('fa-bars');
		        }).fail(function() {
		            alert( "Error while trying to save the new position. Please refresh page." );
		        });
		} 
	});
    
});



</script>
<div class="modal fade" id="addLabelForm" aria-hidden="true" aria-labelledby="addLabelForm"  role="dialog" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" aria-hidden="true" data-dismiss="modal">×</button>
          <h4 class="modal-title"><?= __d('Uskur/WebpageManager','New Folder');?></h4>
        </div>
        <?= $this->Form->create($fileFolder,['id'=>'newFolderForm','url'=>['action'=>'add','_ext'=>'json']]) ?>
        <div class="modal-body">
        
            <?php
                echo $this->Form->input('name',['id'=>'new-name']);
                echo $this->Form->input('foreign_key',['value'=>$fk,'type'=>'hidden']);
            ?>
        
        </div>
        <div class="modal-footer">
        	<?= $this->Form->button(__d('Uskur/WebpageManager','Submit')) ?>
          <a class="btn btn-sm btn-white" data-dismiss="modal" href="javascript:void(0)">Cancel</a>
        </div>
        
        <?= $this->Form->end() ?>
      </div>
    </div>
  </div>
  <div class="modal fade" id="renameFolderModal" aria-hidden="true" aria-labelledby="renameFolderModal"  role="dialog" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" aria-hidden="true" data-dismiss="modal">×</button>
          <h4 class="modal-title"><?= __d('Uskur/WebpageManager','Rename Folder');?></h4>
        </div>
        <?= $this->Form->create(null,['id'=>'renameFolderForm','url'=>['action'=>'edit']]) ?>
        <div class="modal-body">
        
            <?php
                echo $this->Form->input('id',['id'=>'rename-id','type'=>'hidden']);
                echo $this->Form->input('name',['id'=>'rename-name']);
            ?>
        
        </div>
        <div class="modal-footer">
        	<?= $this->Form->button(__d('Uskur/WebpageManager','Submit')) ?>
          <a class="btn btn-sm btn-white" data-dismiss="modal" href="javascript:void(0)">Cancel</a>
        </div>
        
        <?= $this->Form->end() ?>
      </div>
    </div>
  </div>
  <div class="modal fade" id="importFilesModal" aria-hidden="true" aria-labelledby="importFilesModal"  role="dialog" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" aria-hidden="true" data-dismiss="modal">×</button>
          <h4 class="modal-title"><?= __d('Uskur/WebpageManager','Import Files');?></h4>
        </div>
        <?= $this->Form->create(null,['id'=>'importFilesForm','url'=>['action'=>'import']]) ?>
        <div class="modal-body">
        
            <?php
                echo $this->Form->input('id',['id'=>'import-id','type'=>'hidden']);
                echo $this->Form->input('source_type',['id'=>'import-source-type','options'=>['local'=>__d('Uskur/WebpageManager','Local')]]);
                echo $this->Form->input('source',['id'=>'import-source']);
            ?>
        
        </div>
        <div class="modal-footer">
        	<?= $this->Form->button(__d('Uskur/WebpageManager','Submit')) ?>
          <a class="btn btn-sm btn-white" data-dismiss="modal" href="javascript:void(0)">Cancel</a>
        </div>
        
        <?= $this->Form->end() ?>
      </div>
    </div>
  </div>
  <div class="modal fade" id="fileAttributes" aria-hidden="true" aria-labelledby="fileAttributes"  role="dialog" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" aria-hidden="true" data-dismiss="modal">×</button>
          <h4 class="modal-title"><?= __d('Uskur/WebpageManager','Edit File Attributes');?></h4>
        </div>
        <?= $this->Form->create(null,['id'=>'fileAttributeForm','url'=>['action'=>'fileAttribute']]) ?>
        <div class="modal-body">
        <p><strong>File Name:</strong> <span id="attribute-name"></span></p>
            <?php
                echo $this->Form->input('id',['id'=>'attribute-id','type'=>'hidden']);
                echo $this->Form->input('title',['id'=>'attribute-title']);
                echo $this->Form->input('description',['id'=>'attribute-description','type'=>'textarea']);
                echo $this->Form->input('link',['id'=>'attribute-link']);
            ?>
        
        </div>
        <div class="modal-footer">
        	<?= $this->Form->button(__d('Uskur/WebpageManager','Submit')) ?>
          <a class="btn btn-sm btn-white" data-dismiss="modal" href="javascript:void(0)">Cancel</a>
        </div>
        
        <?= $this->Form->end() ?>
      </div>
    </div>
  </div>
<?php echo $this->end();?>

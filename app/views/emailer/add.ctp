<?php $readonly = isset($readonly) ? $readonly : false;?>
<table width="100%"  border="0" cellpadding="8" cellspacing="0" bgcolor="#FFFFFF">
  <tr>
    <td>
    <form name="frm" id="frm" method="POST" action="<?php echo $html->url(empty($this->data['EmailTemplate']['id'])?'add':'edit/'.$this->data['EmailTemplate']['id']) ?>">

      <?php echo empty($this->data['EmailTemplate']['id']) ?
              $form->hidden('EmailTemplate.user_id', array('value'=>$currentUser['id'])) :
              $form->hidden('EmailTemplate.id', array('value'=>$this->data['EmailTemplate']['id'])); ?>
      <?php //echo empty($params['data']['EmailTemplate']['id']) ? $form->hidden('EmailTemplate.creator_id', array('value'=>$currentUser['id'])) : $form->hidden('EmailTemplate.updater_id', array('value'=>$currentUser['id'])); ?>
      <input type="hidden" name="assigned" id="assigned" />
      <table width="95%" border="0" align="center" cellpadding="4" cellspacing="2">
  <tr class="tableheader">
  <td colspan="3" align="center">
    <?php
      echo $readonly ?
            ('View') :
            (empty($this->data['EmailTemplate']['id'])?'Add':'Edit');
    ?> Custom Email Template</td>
  </tr>
  <tr class="tablecell2">
  	<td id="newemail_label">Custom Email Name:&nbsp;<font color="red">*</font></td>
  	<td>
        <?php echo $form->input('EmailTemplate.name', array('size'=>'50','label'=>false,'readonly'=>$readonly));?>
        </td>
        <td>&nbsp;</td>
  </tr>

  <tr class="tablecell2">
    <td>Availability:</td>
    <td>
      <?php
        echo $form->input('EmailTemplate.availability', array(
           'type' => 'radio',
           'options' => array('0' => ' - Private&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;',
                              '1' => ' - Public&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;',
                              '2' => ' - shared&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'),
           'default' => '0',
           'legend' => false,
           'disabled'=>$readonly
        ));
      ?>
    </td>
    <td>&nbsp;</td>
  </tr>

  <tr class="tablecell2">
    <td>Description:&nbsp;</td>
    <td><?php echo $form->textarea('EmailTemplate.description', array('rows' => '5', 'style'=>'width:70%;', 'readonly'=>$readonly)) ?>  </td>
    <td>&nbsp;</td>
  </tr>

  <tr class="tablecell2">
    <td>Content:&nbsp;</td>
    <td><?php echo $form->textarea('EmailTemplate.content', array('rows' => '30', 'style'=>'width:70%;', 'readonly'=>$readonly)) ?>  </td>
    <td>&nbsp;</td>
  </tr>

  <tr class="tablecell2">
    <td colspan="3" align="center"><?php if (!$readonly) echo $this->Form->submit() ?></td>
  </tr>
</table>
    </form>
	</td>
  </tr>
</table>
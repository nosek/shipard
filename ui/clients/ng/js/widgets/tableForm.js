class ShipardTableForm extends ShipardCoreForm
{
  init(e)
  {
    console.log("ShipardTableForm::init");
    super.init(e);
    this.rootElm.style.display = 'grid';
  }

  create(e)
  {
    let apiParams = {
      'cgType': 2,
      'formOp': e.formOp,
    };

    this.elementPrefixedAttributes (e, 'data-action-param-', apiParams);

    this.apiCall('createForm', apiParams);
  }

  doAction (actionId, e)
  {
    //console.log("form action!", actionId);
    switch (actionId)
    {
      case 'saveForm': return this.saveForm(e);
      case 'saveform': return this.saveForm(e);
      case 'closeForm': return this.closeForm(e);
    }

    return super.doAction(actionId, e);
  }

  saveForm(e)
  {
    const noClose = parseInt(e.getAttribute('data-noclose'));

    this.getFormData();

    let apiParams = {
      'cgType': 2,
      'formOp': 'save',
      'formData': this.formData,
      'noCloseForm': noClose,
    };


    this.elementPrefixedAttributes (this.rootElm, 'data-action-param-', apiParams);
    this.elementPrefixedAttributes (e, 'data-action-param-', apiParams);

    this.apiCall('saveForm', apiParams);

    return 0;
  }

  checkForm(changedInput)
  {
    this.getFormData();

    let apiParams = {
      'cgType': 2,
      'formOp': 'check',
      'formData': this.formData,
      'noCloseForm': 1,
    };


    this.elementPrefixedAttributes (this.rootElm, 'data-action-param-', apiParams);
    //this.elementPrefixedAttributes (e, 'data-action-param-', apiParams);

    this.apiCall('checkForm', apiParams);

    return 0;
  }

  doWidgetResponse(data)
  {
    //console.log("doWidgerResponse / FORM: ", data['response']['type']);

    if (data['response']['type'] === 'createForm')
    {
      this.rootElm.innerHTML = data['response']['hcFull'];
      this.setFormData(data['response']['formData']);

      this.on(this, 'change', 'input', function (e, ownerWidget){ownerWidget.inputValueChanged(e)});

      return;
    }
    if (data['response']['type'] === 'saveForm')
    {
      //console.log("---SAVE-FORM---", data['response']);

      let noCloseForm = data['response']['saveResult']['noCloseForm'] ?? 0;
      //console.log('noCloseForm: ', noCloseForm);

      if (!noCloseForm)
      {
        const parentWidgetType = this.rootElm.getAttribute('data-parent-widget-type');
        //console.log('parentWidgetType: ', parentWidgetType);
        if (parentWidgetType === 'viewer')
        {
          const parentWidgetId = this.rootElm.getAttribute('data-parent-widget-id');
          if (parentWidgetId)
          {
            const parentElement = document.getElementById(parentWidgetId);
            if (parentElement)
              parentElement.shpWidget.refreshData();
          }
        }
        else if (parentWidgetType === 'board')
        {
          const parentWidgetId = this.rootElm.getAttribute('data-parent-widget-id');
          if (parentWidgetId)
          {
            const parentElement = document.getElementById(parentWidgetId);
            if (parentElement)
              parentElement.shpWidget.refreshData();
          }
        }

        this.closeForm();
        return;
      }

      this.rootElm.innerHTML = data['response']['hcFull'];
      this.setFormData(data['response']['formData']);
      return;
    }

    if (data['response']['type'] === 'checkForm')
    {
      //console.log("---CHECK-FORM---", data['response']);
      this.rootElm.innerHTML = data['response']['hcFull'];
      this.setFormData(data['response']['formData']);
      return;
    }

    super.doWidgetResponse(data);
  }

  closeForm(e)
  {
    this.rootElm.remove();

    return 0;
  }

  inputValueChanged(e)
  {
    //console.log("--INPUT-CHANGED--", e);
    if (e.type === 'file')
    {
      console.log('form-file input CHANGED');
      this.checkFileUploader(e);
      return;
    }

    if (e.classList.contains('e10-ino-checkOnChange'))
    {
      this.checkForm(e);
    }
  }

  checkFileUploader(input)
  {
    console.log('checkFileUploader');
    let fileUploaderElm = input.parentElement;
    if (fileUploaderElm.fileUploader === undefined)
    {
      fileUploaderElm.fileUploader = new ShipardFilesUploader();
      fileUploaderElm.fileUploader.init(fileUploaderElm);
    }
    fileUploaderElm.fileUploader.resetInfo();
  }
}

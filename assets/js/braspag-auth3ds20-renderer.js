var BpmpiRenderer = Class.create();

BpmpiRenderer.prototype = {
  initialize: function() {
    
  },
  
  renderBpmpiData: function (item, element, value) {
    
    if (element && element.length > 0) {
      value = element.val();
    }
    
    if (item) {
      jQuery('.'+item).val(value);
    }
  },
  
  createInputHiddenElement: function(appendToElement, elementName, elementClass, value) {
    
    if (elementName != '' && appendToElement.find("input[name='"+elementName+"']").length == 0) {
      appendToElement.append(
        jQuery('<input>')
          .attr('type', 'hidden')
          .attr('name', elementName)
          .addClass(elementClass)
      );
      
      this.renderBpmpiData(elementClass, false, value);
      
    } else if (elementClass != '' && appendToElement.find("input[class='"+elementClass+"']").length == 0) {
      
      appendToElement.append(
        jQuery('<input>')
          .attr('type', 'hidden')
          .addClass(elementClass)
      );
      
      this.renderBpmpiData(elementClass, false, value);
    }
  },
}

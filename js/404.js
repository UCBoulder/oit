(function ($) {
  $(document).ready(function () {

    $('.spin').on( 'click touch', function () {
      var x = 0; //min value
      var y = 5400; // max value
      var deg = Math.floor(Math.random() * (y - x)) + x;

      document.getElementById('box').style.transform = "rotate(" + deg + "deg)";

      //GENERATE URL
      var bsURL = ['15947','23541','21301','15997','23451','2020','16587','8615','22576','17307','2541','15003','8616','16521','11375','11449','11477','11445'];
      var bsI = parseInt(Math.random() * bsURL.length);

      var iamURL = ['16743','3174','3385','3176','15977','1169','1226','1182','1171','21526','22021','30441','22136','22091','22096','22536','22116','22081','22151','1168','2196','4988'];
      var iamI = parseInt(Math.random() * iamURL.length);

      var mcURL = ['388','1847','391','6773','377','383','385','392','16507','11613','13495','13489','25711','11605','11607','24111','10617','11389','11587','11061','11585','10743','237','12589','16475','15807','17093','15939','15811','16651','17099','16263','16299','26696','16489','16487','16491','16685','16069','16455','15729','16409','16427','16077','16423','16407','16637','16091','16431','16089','16079','16639','16479','394','15009','15007'];
      var mcI = parseInt(Math.random() * mcURL.length);

      var vcURL = ['1917','1913','1914','1916','1915','1037','15829','17039','24281','1034','2136','16817','16797','1033'];
      var vcI = parseInt(Math.random() * vcURL.length);

      var itsecURL = ['575','410','21846'];
      var itsecI = parseInt(Math.random() * itsecURL.length);

      var netURL = ['1884','15587','15583','15589','15591','15585','5514','5528','1883','248','8594','425','881','1010','1699','612','613','1956','673','573','584','582','583','574','578','588','14671','14673','16275','14667','14669','595','585','586','739','737','738','1731','30581'];
      var netI = parseInt(Math.random() * netURL.length);

      var lstURL = ['413','1626','511','1625','24881','24891','847','24896','25146','20926','239','1788','415','1485'];
      var lstI = parseInt(Math.random() * lstURL.length);

      var tlaURL = ['21566','23541','428','1046','19026','22411','19636','19121','23601','21701','20251','20041','19036','19126','20256','19596','20206','21506','16361','418','12097','1425','1426','20941','1427','1428','18701','16597','22296','24891','774','572','8689','847','24896','25146','571','243','867','778','22921','19671','17029','17031','19481','2326','19816','19716','3986','19726','19776','3984','15387','2327','26106','24676','24666','24661','24656','24686','24651','22576','17307','15003','24056','1770','20531','3092','20546','2323','19491','10101','2542','24511','15013','21731','21851','26441','25631','15015','21856','24376','24381','21971'];
      var tlaI = parseInt(Math.random() * tlaURL.length);

      var oitURL = 'https://oit.colorado.edu/node/';

      //POSITION CHOICE FUNCTION
      setTimeout(function () {
        var prizeHTML = document.getElementById('prize');
        var wheelRot = getRotationAngle(document.getElementById('box'));
        console.log(wheelRot)
        if (wheelRot >= 0 && wheelRot <= 45) {
          prizeHTML.innerHTML = '<a href="' + oitURL + bsURL[bsI] + '">Click here to go to your Business Services prize page!</a>';
          console.log(oitURL + bsURL[bsI]);
        }    else if (wheelRot >= 46 && wheelRot <= 90) {
          prizeHTML.innerHTML = '<a href="' + oitURL + itsecURL[itsecI] + '">Click here to go to your IT Security prize page!</a>';
          console.log(oitURL + itsecURL[itsecI]);
        }    else if (wheelRot >= 91 && wheelRot <= 135) {
          prizeHTML.innerHTML = '<a href="' + oitURL + netURL[netI] + '">Click here to go to your Networking prize page!</a>';
          console.log(oitURL + netURL[netI]);
        }    else if (wheelRot >= 136 && wheelRot <= 180) {
          prizeHTML.innerHTML = '<a href="' + oitURL + iamURL[netI] + '">Click here to go to your Identity and Access Management prize page!</a>';
          console.log(oitURL + iamURL[iamI]);
        } else if (wheelRot >= 181 && wheelRot <= 225) {
          prizeHTML.innerHTML = '<a href="' + oitURL + vcURL[vcI] + '">Click here to go to your Voice Communications prize page!</a>';
          console.log(oitURL + vcURL[vcI]);
        } else if (wheelRot >= 226 && wheelRot <= 270) {
          prizeHTML.innerHTML = '<a href="' + oitURL + lstURL[lstI] + '">Click here to go to your Learning Spaces Technology prize page!</a>';
          console.log(oitURL + lstURL[lstI]);
        } else if (wheelRot >= 271 && wheelRot <= 315) {
          prizeHTML.innerHTML = '<a href="' + oitURL + tlaURL[tlaI] + '">Click here to go to your Teaching and Learning Applications prize page!</a>';
        } else if (wheelRot >= 316 && wheelRot <= 360) {
          prizeHTML.innerHTML = '<a href="' + oitURL + mcURL[mcI] + '">Click here to go to your Messaging and Collaboration prize page!</a>';
        }
        }, 3000);
    });

    //GET SPINNER ROTATION
    function getRotationAngle(target)
    {
      const obj = window.getComputedStyle(target);
      const matrix = obj.getPropertyValue('-webkit-transform') ||
        obj.getPropertyValue('-moz-transform') ||
        obj.getPropertyValue('-ms-transform') ||
        obj.getPropertyValue('-o-transform') ||
        obj.getPropertyValue('transform');

      let angle = 0;

      if (matrix !== 'none')
      {
        const values = matrix.split('(')[1].split(')')[0].split(',');
        const a = values[0];
        const b = values[1];
        angle = Math.round(Math.atan2(b, a) * (180 / Math.PI));
      }

      return (angle < 0) ? angle += 360 : angle;
    }

    // TOGGLE BUFF AND SPIN

    $('#toggle404').on( 'click touch', function () {
      $('#buff404').animate({
        opacity: 0,
        height: 0
        }, "slow", function () {
          $('.spin-contain').fadeIn( "slow", "linear");
      });
    });

  });
})(jQuery);

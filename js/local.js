var local=new function(){this.init=function(){function d(a){console.log("onFileSystemSuccess");a.root.getFile("data",null,e,c)}function e(a){console.log("gotFileEntry");a.file(f,c)}function f(a){console.log("gotFile");console.log("readAsText");var b=new FileReader;b.onloadend=function(a){console.log(a.target.result)};b.readAsText(a)}function c(a){console.log("fail");for(var b in a.target.error)console.log(b+": "+a.target.error[b]);dlg.error(a.target.error.code)}console.log(")))))))))))))))");document.addEventListener("deviceready",
function(){console.log("onDeviceReady");window.requestFileSystem(LocalFileSystem.PERSISTENT,0,d,c)},!1)};this.sync=function(){}};

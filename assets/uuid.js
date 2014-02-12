/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */




window.addEvent('domready', function() {
    // some code which loads new elements by ajax

    // Filter
    $$('#runner_single_src').addEvent('click', function(event) {
        alert('Bare in mind, this may take a while...');
    });
    $$('#runner_multi_src').addEvent('click', function(event) {
        alert('Bare in mind, this may take a while...');
    });
});
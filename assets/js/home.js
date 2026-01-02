let zIndex = 20;
let currentPath = '/';

/**
 * Carga inicial del escritorio con carpetas y archivos.
 */
async function loadDesktop() {
    const container = $('#desktop');
    container.empty();

    try {
        const res = await fetch('/hdrive/api/listFolder?path=/');
        const data = await res.json();

        if (data.items && data.items.length > 0) {
            data.items.forEach(item => {
                if (item.type === 'folder') {
                    container.append(`<div class="icon" ondblclick="openDrive('${item.path}')">📁<span>${item.name}</span></div>`);
                } else {
                    container.append(`<div class="icon" ondblclick="openFile('${item.path}')">📄<span>${item.name}</span></div>`);
                }
            });
        } else {
            container.append('<p>No hay archivos en la raíz</p>');
        }
    } catch (err) {
        console.error(err);
        container.append('<p>Error cargando escritorio</p>');
    }
}

/**
 * Abre una carpeta en el explorador.
 */
async function openDrive(path) {
    currentPath = path;
    $('#path').text('Mi Drive ' + path);
    await renderFiles();
    openWindow('#explorer');
}

/**
 * Renderiza los archivos y carpetas de la carpeta actual.
 */
async function renderFiles() {
    const container = $('#files');
    container.empty();

    if (currentPath !== '/') {
        container.append(`<div class="file" onclick="openDrive('/')">⬅️ Volver</div>`);
    }

    try {
        const res = await fetch('/hdrive/api/listFolder?path=' + encodeURIComponent(currentPath));
        const data = await res.json();

        if (data.items && data.items.length > 0) {
            data.items.forEach(item => {
                if (item.type === 'folder') {
                    container.append(`<div class="file" onclick="openDrive('${item.path}')">📁 ${item.name}</div>`);
                } else {
                    container.append(`<div class="file" onclick="openFile('${item.path}')">📄 ${item.name}</div>`);
                }
            });
        } else {
            container.append('<div class="file">No hay archivos</div>');
        }
    } catch (err) {
        console.error(err);
        container.append('<div class="file">Error cargando carpeta</div>');
    }
}

/**
 * Abre un archivo en el visor detectando tipo.
 */
async function openFile(path) {
    try {
        const res = await fetch('/hdrive/api/fileInfo?path=' + encodeURIComponent(path));
        const data = await res.json();

        $('#fileName').text(data.name);
        const ext = data.extension ? data.extension.toLowerCase() : '';

        if (data.content !== null) {
            // Texto
            $('#fileContent').html('<pre>' + $('<div>').text(data.content).html() + '</pre>');
        } else if (data.url) {
            if (['png','jpg','jpeg','gif','bmp'].includes(ext)) {
                $('#fileContent').html('<img src="' + data.url + '" style="max-width:100%; max-height:100%"/>');
            } else if (ext === 'pdf') {
                $('#fileContent').html('<embed src="' + data.url + '" type="application/pdf" width="100%" height="100%">');
            } else if (['mp3','wav','ogg'].includes(ext)) {
                $('#fileContent').html('<audio controls src="' + data.url + '" style="width:100%"></audio>');
            } else if (['mp4','webm','ogg'].includes(ext)) {
                $('#fileContent').html('<video controls src="' + data.url + '" style="max-width:100%; max-height:100%"></video>');
            } else {
                $('#fileContent').html('<a href="' + data.url + '" target="_blank">Descargar archivo</a>');
            }
        } else {
            $('#fileContent').html('<pre>Archivo vacío o no soportado</pre>');
        }

        openWindow('#viewer');
    } catch (err) {
        console.error(err);
        $('#fileName').text(path);
        $('#fileContent').html('<pre>No se pudo cargar el archivo</pre>');
        openWindow('#viewer');
    }
}

/**
 * Funciones de ventana
 */
function openWindow(sel) {
    $(sel).show().css('z-index', ++zIndex);
}
function closeWindow(sel) {
    $(sel).hide();
}

/**
 * Habilita drag & drop para subir archivos a escritorio o carpeta.
 */
function enableDragAndDrop(target) {
    if(!target) return;

    target.addEventListener('dragover', e => {
        e.preventDefault();
        e.stopPropagation();
        target.classList.add('dragover');
    });

    target.addEventListener('dragleave', e => {
        e.preventDefault();
        e.stopPropagation();
        target.classList.remove('dragover');
    });

    target.addEventListener('drop', async e => {
        e.preventDefault();
        e.stopPropagation();
        target.classList.remove('dragover');

        const files = e.dataTransfer.files;
        if(files.length === 0) return;

        const formData = new FormData();
        Array.from(files).forEach(f => formData.append('file', f));

        const path = (target.id === 'desktop') ? '/' : currentPath;

        try {
            const res = await fetch('/hdrive/api/uploadFile?path=' + encodeURIComponent(path), {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if(data.success){
                console.log('Archivo subido:', data.file);
                if(target.id === 'desktop'){
                    await loadDesktop();
                } else {
                    await renderFiles();
                }
            } else {
                alert('Error al subir archivo');
            }
        } catch(err){
            console.error(err);
            alert('Error al subir archivo');
        }
    });
}

$(function(){
    // Ventanas draggable
    $('.window').draggable({
        handle:'.titlebar',
        containment:'body',
        start:function(){ $(this).css('z-index',++zIndex); }
    });

    // Inicializar drag & drop
    enableDragAndDrop(document.getElementById('desktop'));
    enableDragAndDrop(document.getElementById('explorer'));

    // Carga inicial del escritorio
    loadDesktop();
});

/**
 * Fetches objects from a CSV file and displays them as a list.
 */



function miFuncion() {
    alert('¡Hola desde mi función JavaScript!')
};






function fetchObjectsFromCSV() {
    fetch('http://150.128.81.34:8000/get_objects_from_csv') ////150.128.81.34   // fetch('http://localhost:8000/get_objects_from_csv')
        .then(response => response.json())
        .then(data => {
            // Crear la lista ordenada HTML
            const list = document.createElement('ol');
            list.id = 'objectList'; // Asignar un id a la lista
            // Crear los elementos de lista con los datos de los objetos
            data.forEach(obj => {
                const listItem = document.createElement('li');
                listItem.textContent = `ID: ${obj.id}, Title: ${obj.title}`;
                listItem.classList.add('object-item'); // Agregar clase CSS para estilizar los elementos
                // Agregar evento de clic a los elementos de lista
                listItem.addEventListener('click', () => {
                    const listItems = document.querySelectorAll('#objectList li');
                    listItems.forEach(item => {
                        item.style.fontWeight = 'normal';
                    });
                    listItem.style.fontWeight = 'bold';
                    // Llamar a la función recommend para enviar el ID del objeto
                    const id = obj.id;
                    fetchRecommendations(id);
                });
                // Agregar evento de mouseover a los elementos de lista
                listItem.addEventListener('mouseover', () => {
                    listItem.style.cursor = 'pointer';
                });
                // Agregar el elemento de lista a la lista ordenada
                list.appendChild(listItem);
            });
            // Ajustar el estilo de la lista
            list.style.padding = '0';
            // Agregar la lista al contenedor HTML deseado (por ejemplo, un div con id 'content')
            const contentContainer = document.getElementById('page-content');
            contentContainer.appendChild(list);
        })
        .catch(error => {
            console.error('Error al obtener los objetos:', error);
        });
}

function fetchRecommendations_plainText(id = 1) {
    fetch(`http://150.128.81.34:8000/recommend/${id}`)
        .then(response => response.json())
        .then(data => {

            alert(data)
            // Mostrar el diccionario resultante en la página web
            const dictionaryContainer = document.createElement('div');
            dictionaryContainer.id = 'dictionaryContainer'; // Asignar un id al contenedor
            dictionaryContainer.textContent = JSON.stringify(data);
            // Agregar el contenedor al documento HTML
            document.body.appendChild(dictionaryContainer);
        })
        .catch(error => {
            console.error('Error al obtener las recomendaciones:', error);
        });
}


function fetchRecommendations(id = 4527987) { //id por defecto 4527987 por no poner 1
    fetch(`http://150.128.81.34:8000/recommend/${id}`)
        .then(response => response.json())
        .then(data => {
            // Crear el contenedor para las nuevas recomendaciones
            const newRecommendationsContainer = document.createElement('div');
            newRecommendationsContainer.id = 'newRecommendationsContainer'; // Asignar un id al contenedor

            // Crear la tabla HTML
            const table = document.createElement('table');
            table.id = 'recommendationsTable'; // Asignar un id a la tabla

            // Crear la fila de encabezados de la tabla
            const headerRow = document.createElement('tr');
            for (const header of Object.keys(data)) {
                const headerCell = document.createElement('th');
                headerCell.textContent = header;
                headerRow.appendChild(headerCell);
            }
            table.appendChild(headerRow);

            // Crear el cuerpo de la tabla
            const tableBody = document.createElement('tbody');

            // Crear las filas de la tabla con los datos del diccionario
            const columnKeys = Object.keys(data);
            const rowCount = data[columnKeys[0]].length; // Se asume que todas las columnas tienen la misma longitud
            for (let i = 0; i < rowCount; i++) {
                const row = document.createElement('tr');
                for (const column of columnKeys) {
                    const cell = document.createElement('td');
                    cell.textContent = data[column][i];
                    row.appendChild(cell);
                }
                tableBody.appendChild(row);
            }

            // Agregar el cuerpo de la tabla al elemento table
            table.appendChild(tableBody);

            // Limpiar el contenido del contenedor de nuevas recomendaciones
            const existingNewRecommendationsContainer = document.getElementById('newRecommendationsContainer');
            if (existingNewRecommendationsContainer) {
                existingNewRecommendationsContainer.innerHTML = '';
            }

            // Agregar la tabla al contenedor de nuevas recomendaciones
            newRecommendationsContainer.appendChild(table);

            // Obtener el objeto con el ID 'objectList'
            const objectList = document.getElementById('objectList');

            // Insertar el contenedor de nuevas recomendaciones después del objeto objectList
            objectList.parentNode.insertBefore(newRecommendationsContainer, objectList.nextSibling);
        })
        .catch(error => {
            console.error('Error al obtener las recomendaciones:', error);
        });
}





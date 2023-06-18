document.querySelectorAll(".button").forEach((button) =>
    button.addEventListener("click", (e) => {
        if (!button.classList.contains("loading")) {
            button.classList.add("loading");
            setTimeout(() => button.classList.remove("loading"), 3700);
        }
        e.preventDefault();

        var json_text = document.getElementById('myTextArea').value;
        var json_data = JSON.parse(json_text);
        
        var json_data = {
            "itemID": json_data.itemID,
            "merchantID": json_data.merchantID,
            "sku": json_data.sku,
            "price": json_data.price,
            "quantity": json_data.quantity,
            "description": json_data.description,
            "name": json_data.name
        }

        console.log(json_data);

        var settings = {
            "url": "http://localhost:3000/simple_general_endpoint.php",
            "method": "POST",
            "data": JSON.stringify(json_data)
        };

        $.ajax(settings).done(function (response) {
            console.log(response)
        });
    })
);

function prettyPrint() {
    var ugly = document.getElementById('myTextArea').value;
    var obj = JSON.parse(ugly);
    var pretty = JSON.stringify(obj, undefined, 4);
    document.getElementById('myTextArea').value = pretty;
}

window.onload = function() {

    console.log("<<=========>> PEW PEW <<=======>>");

    var deliverance = new Deliverance({
        jwt: "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJuYW1lIjoiQ2FuZGVsYSIsImlzcyI6IjEyNy4wLjAuMSIsImlhdCI6MTMwMDgxOTM4MCwic2NvcGUiOlsiYWRkX2V2ZW50Il19.14mdTPoTQZtlIsr93D8uWnFGc5ntUHzr7clUm3uE_Mw",
        endpoint: "https://127.0.0.1:444/report"
    });

    deliverance.send({
        user_id: 4,
        type: 'page_load',
        guid: '113858-a211dfgh-6fg654fgh-lki1gjhk54-fre5321',
        data: {
            fruit: "banana",
            vegetable: "Carrot",
            dairy: "Cheese",
            meat: "Alligator",
            people: [
                "Dave",
                "Sally",
                "George",
                "Kelly",
                "Dillon",
                "Sven",
                "Chelsea"
            ]
        }
    }, function (err, res) {
        if (err) return console.error("Err:", err, res);

        console.log(res);
    });

};


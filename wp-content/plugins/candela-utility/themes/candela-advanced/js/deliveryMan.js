
window.onload = function() {
    var outcomes = document.getElementById('outcome_description'),
        deliverance = new Deliverance({
        jwt: "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJuYW1lIjoiQ2FuZGVsYSIsImlzcyI6IjEyNy4wLjAuMSIsImlhdCI6MTMwMDgxOTM4MCwic2NvcGUiOlsiYWRkX2V2ZW50Il19.14mdTPoTQZtlIsr93D8uWnFGc5ntUHzr7clUm3uE_Mw",
        endpoint: "https://127.0.0.1:444/report"
    });

    deliverance.send({
        user_id: Number(outcomes.dataset.userId),
        type: 'page_load',
        guid: outcomes.dataset.outcomeGuids.split(', '), //creates array of guids
        data: {}
    }, function (err, res) {
        if (err) return console.error("Err:", err, res);

        console.log(res);
    });

};


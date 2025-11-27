@extends('layouts.app')

@section('title')
Image Test
@endsection

@section('content')
    <div class="container-fluid">
        <div>
            <canvas id="input_image" width="800" height="1000" class="droptarget" />
            <span id="coords"></span>
        </div>
        <div class="pull-right">
            <ul id="fields">
                <li draggable="true">Person.FirstName</li>
                <li draggable="true">Person.LastName</li>
                <li draggable="true">Person.Address</li>
            </ul>
        </div>
    </div>

@endsection

@section('head')
<style scoped>
canvas {
    border: 1px solid black;
    background-color: white;
}
</style>
@endsection

@section('scripts')
<script>
    window.onload = function() {
        var input_image = document.getElementById("input_image");
        var ctx = input_image.getContext("2d");
        // ctx.style.color = "white";
        ctx.font = "12px Arial";

        input_image.addEventListener("dragenter", function(event) {
            if ( event.target.className == "droptarget" ) {
                event.target.style.border = "1px dotted red";
            }
        });

        input_image.addEventListener("dragover", function(event) {
            event.preventDefault();
            var x = event.clientX - event.target.offsetLeft;
            var y = event.clientY - event.target.offsetTop;
            var coords = "(" + x + ", " + y + ")";
            document.getElementById("coords").innerText = coords;
        });

        input_image.addEventListener("dragleave", function(event) {
            if ( event.target.className == "droptarget" ) {
                event.target.style.border = "1px solid black";
            }
        });
        
        input_image.addEventListener("drop", function(event) {
            event.preventDefault();
            if ( event.target.className == "droptarget" ) {
                var x = event.clientX - event.target.offsetLeft;
                var y = event.clientY - event.target.offsetTop;
                event.target.style.border = "";
                var data = event.dataTransfer.getData("Text");

                var drop_results = "" + data + " (" + x + ", " + y + ")";

                ctx.fillText(data, x, y);
                console.log(drop_results);
            }
        });

        document.querySelectorAll('[draggable="true"]').forEach(function(element) {
            element.addEventListener("dragstart", function(event) {
                event.dataTransfer.setData("Text", this.innerText);
            });
        });
    };
</script>
@endsection
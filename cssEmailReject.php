<?php
header('Content-Type: text/css');

?>

body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
    line-height: 1.6;
    color: #333;
}

table {
    width: 60%;
    border-collapse: collapse;
    margin: 20px auto; 
    font-size: 16px;
    border: 1px solid #ddd;
}

th, td {
    text-align: left;
    padding: 12px;
    border: 1px solid #ddd;
}

th {
    background-color: #f2f2f2;
    font-weight: bold;
}

td.remarks {
    height: 100px; 
    vertical-align: top;
    text-align: justify; 
}

td.reason {
    text-align: justify; 
}

h3, p {
    text-align: justify;
    margin: 10px 20px;
}

strong {
    font-weight: bold;
}
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">Chat</div>
                <div class="card-body">
                    <div id="chat-app">
                        @if ($chat_enabled == '1' || $chat_enabled == 1)
                        <chat-app :user="{{ auth()->user() }}"></chat-app>
                        @else
                        <span>Chat is currently offline.</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('head')
<style>
#chat-app {
  height: 200px;
  width: 75px;
  position: fixed;
  z-index: 1;
  top: 0;
  right: 0;
  background-color: #e4e5e6;
  overflow-x: hidden;
  padding-top: 60px;
  transition: 0.5s;
  border: 1px solid black;
}
</style>
@endsection

@section('scripts')
<script>
window.addEventListener('load', setup);

const get = document.getElementById.bind(document);
const query = document.querySelector.bind(document);

function setup() {

  let modalRoot = get('app');
  let button = get('mail_icon');
  let modal = get('chat-app');

  modalRoot.addEventListener('click', rootClick);
  button.addEventListener('click', openModal);
  modal.addEventListener('click', modalClick);

  function rootClick() {
    modal.display = "block";
  }

  function openModal() {
    modal.display = "hidden";
  }

  function modalClick(e) {
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    return false;
  }

}
</script>
@endsection

<?php defined('BASEPATH') || exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$csrfToken = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;
?>
<style>
@keyframes indeterminateBar {
  0% {
    transform: translateX(-100%);
  }
  50% {
    transform: translateX(50%);
  }
  100% {
    transform: translateX(100%);
  }
}

.animate-indeterminate-bar {
  animation: indeterminateBar 1.6s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}
</style>

<div id="wrapper" class="h-90vh flex flex-col font-sans">
  <div class="content">
    <div id="app">
      <section :class="isSidebarOpen ? 'grid grid-cols-1 md:grid-cols-4 no-gap' : 'grid grid-cols-1 md:grid-cols-5 no-gap'" class="h-90vh transition-all duration-300" style="box-shadow: rgba(100, 100, 111, 0.2) 0px 7px 29px 0px;">
        <!-- Collapsible Sidebar -->
        <aside :class="isSidebarOpen ? 'col-span-1 md:w-100' : 'w-0 col-span-0'" class="bg-gray-100 border-r border-gray-300 overflow-hidden flex flex-col transition-all duration-300">
          <!-- Sidebar Header -->
          <header class="bg-gray-200 p-4 border-b border-gray-300" style="background: linear-gradient(360deg, #f0f4f8, #fff) !important;">
            <div class="flex items-center justify-between w-full">
              <!-- Left Section: Profile Info and Unread Badge -->
              <div class="flex items-center">
                <img :src="whatsapp_selectedProfilePicture" alt="Profile Picture" class="h-10 w-10 rounded-full object-cover">
                <div class="ml-3">
                  <h1 class="font-bold text-lg text-gray-900">{{ whatsapp_selectedProfileName }}</h1>
                </div>
              </div>

              <!-- Right Section: Loading Indicator and Modal Button -->
              <div class="flex items-center">
                <!-- Display Total Unread Count -->
                <div v-if="totalUnread > 0" class="mt-1">
                  <span class="bg-red-500 text-white rounded-full px-2 py-1 text-xs font-bold">
                    {{ totalUnread }} Unread
                  </span>
                </div>
                <button @click="openModal" class="bg-white-600 hover:bg-white-700 text-white font-semibold px-4 py-2 rounded-lg shadow-md flex items-center">
                  ➕
                </button>
              </div>
            </div>
            <!-- Loading Bar -->
<div v-if="loading" class="w-full h-2 bg-gray-100 rounded overflow-hidden mt-3 relative">
  <div class="absolute inset-0">
    <div class="h-full w-1/2 bg-blue-500 rounded animate-indeterminate-bar"></div>
  </div>
</div>

          </header>


          <div v-if="isSidebarOpen" class="p-3">
            <div class="flex flex-wrap -mx-2 space-y-4 md:space-y-0">

              <!-- Search Input (100% width, occupies one row) -->
              <div class="w-full px-2 mb-4">
                <label for="whatsapp_searchText" class="sr-only">🔍 Search or start new chat</label>
                <input id="whatsapp_searchText" v-model="whatsapp_searchText" type="text" @keyup="whatsapp_filterInteractions"
                  class="form-control w-full border border-gray-300 p-2 focus:outline-none focus:ring-2 focus:ring-blue-500 text-xs"
                  placeholder="🔍 Chat filter">
              </div>

              <!-- WhatsApp Number Filter (100% width) -->
              <div class="w-full px-2 mb-4">
                <label for="whatsapp_selectedWaNo" class="sr-only">📞 Search by WhatsApp Number</label>
                <select class="form-control w-full border border-gray-300 p-2 focus:outline-none focus:ring-2 focus:ring-blue-500 text-xs"
                  v-model="whatsapp_selectedWaNo" @change="whatsapp_filterInteractions" id="whatsapp_selectedWaNo">
                  <option v-for="(interaction, index) in whatsapp_uniqueWaNos"
                    :key="index" :value="interaction.phone_number_id">
                    {{ interaction.phone_number }}
                  </option>
                  <option value="*">📞 Number filter</option>
                </select>
              </div>
              <br><br>
              <!-- Interaction Type Filter (50% width, first row) -->
              <div class="w-full sm:w-1/2 lg:w-1/2 px-2">
                <label for="whatsapp_selectedInteractionType" class="sr-only">💬 Select Interaction Type</label>
                <select class="form-control w-full border border-gray-300 p-2 focus:outline-none focus:ring-2 focus:ring-blue-500 text-xs"
                  v-model="whatsapp_selectedInteractionType" @change="whatsapp_filterInteractions">
                  <option value="*">💬 Type filter</option>
                  <option v-for="type in whatsapp_uniqueInteractionTypes" :key="type" :value="type">
                    {{ type }}
                  </option>
                </select>
              </div>
              <br><br>
              <!-- Status Filter (50% width, first row) -->
              <div class="w-full sm:w-1/2 lg:w-1/2 px-2">
                <label for="whatsapp_selectedStatus" class="sr-only">⚙️ Select Status</label>
                <select class="form-control w-full border border-gray-300 p-2 focus:outline-none focus:ring-2 focus:ring-blue-500 text-xs"
                  v-model="whatsapp_selectedStatus" @change="whatsapp_filterInteractions">
                  <option value="*">⚙️ Status filter</option>
                  <option v-for="status in whatsapp_uniqueStatuses" :key="status.id" :value="status.id">
                    {{ status.name }}
                  </option>
                </select>
              </div>
              <br><br>
              <!-- Assigned Staff Filter (50% width, second row) -->
              <div class="w-full sm:w-1/2 lg:w-1/2 px-2">
                <label for="whatsapp_selectedAssigned" class="sr-only">👨‍💻 Select Assigned Staff</label>
                <select class="form-control w-full border border-gray-300 p-2 focus:outline-none focus:ring-2 focus:ring-blue-500 text-xs"
                  v-model="whatsapp_selectedAssigned" @change="whatsapp_filterInteractions">
                  <option value="*">👨‍💻 Assigned filter</option>
                  <option v-for="staff in whatsapp_uniqueStaff" :key="staff.staffid" :value="staff.staffid">
                    {{ staff.firstname }}
                  </option>
                </select>
              </div>
              <br><br>
              <!-- Unread/Active/Expired Filter (50% width, second row) -->
              <div class="w-full sm:w-1/2 lg:w-1/2 px-2">
                <label for="whatsapp_selectedUnread" class="sr-only">📅 Select Chat Status</label>
                <select class="form-control w-full border border-gray-300 p-2 focus:outline-none focus:ring-2 focus:ring-blue-500 text-xs"
                  v-model="whatsapp_selectedUnread" @change="whatsapp_filterInteractions">
                  <option value="*">📅 Status filter</option>
                  <option value="unread">Unread Chats</option>
                  <option value="active">Active Chats</option>
                  <option value="expired">Expired Chats</option>
                </select>
              </div>
            </div>
          </div>

          <style>
            /* Removing horizontal scroll background */
            .overflow-x-auto {
              background: none !important;
            }
          </style>





          <section v-if="isSidebarOpen" class="overflow-y-auto flex-grow bg-gray-100 p-3" aria-label="Chat Sidebar">
            <ul class="m-0 p-0 list-none">

              <transition-group name="chat" tag="div">
                <li v-for="chat in whatsapp_displayedInteractions" :key="chat.id"
                  @click="whatsapp_selectinteraction(chat.id)"
                  class="cursor-pointer transition-colors duration-200 modern-card modern-list-item rounded-lg mb-2"
                  role="button" tabindex="0"
                  @keydown.enter="whatsapp_selectinteraction(chat.id)"
                  :class="{'bg-gray-200': chat.is_pinned === '0', 'bg-yellow-200': chat.is_pinned === '1'}">

                  <!-- Chat item content -->
                  <div class="flex items-center w-full">
                    <div class="h-12 w-12 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-lg"
                      :aria-label="'Avatar for ' + chat.name">
                      {{ whatsapp_getAvatarInitials(chat.name) }}
                    </div>
                    <div class="ml-3 flex-grow">
                      <header class="flex justify-between items-center w-full" :aria-labelledby="'header-' + chat.id">
                        <div class="flex items-center">
                          <h6 :id="'header-' + chat.id" class="font-bold text-gray-900">{{ chat.name }}</h6>
                          <span v-if="chat.status === 'active'"
                            class="ml-2 h-3 w-3 bg-green-500 rounded-full inline-block"
                            aria-label="Active"></span>
                          <span v-if="chat.status === 'expired'"
                            class="ml-2 h-3 w-3 bg-red-500 rounded-full inline-block"
                            aria-label="Expired"></span>
                        </div>
                        <time class="text-sm text-gray-500 ml-auto" :datetime="chat.time_sent">{{ whatsapp_formatTime(chat.time_sent) }}</time>
                      </header>
                      <div class="flex justify-between items-center mt-1">
                        <p class="text-gray-700 text-sm truncate" v-html="chat.last_message"></p>
                        <ul class="flex gap-1 items-center">
                          <li v-if="chat.unread !== '0'">
                            <span class="modern-unread text-white rounded-full h-5 w-5 flex items-center justify-center bg-blue-500" aria-label="Unread messages">{{ chat.unread }}</span>
                          </li>
                        </ul>
                      </div>

                      <!-- Tags Section -->

                      <!-- Action Buttons -->
                      <div class="mt-2 flex justify-end">
                        <!-- Display Tags as List with Remove Buttons -->
                        <ul class="flex gap-1 flex-wrap items-center">
                          <li v-for="tag in getTagsArray(chat.tags)" :key="tag" class="bg-gray-200 text-gray-800 rounded-full px-2 py-1 text-xs flex items-center">
                            {{ tag }}
                          </li>
                        </ul>

                        <!-- Toggle Add Tag Input -->
                        <button @click.stop="toggleTagInput(chat)" class="text-blue-500 text-xs mt-1" aria-label="Toggle Tag Input">
                          <i :class="chat.showTagInput ? 'fas fa-times' : 'fas fa-tag'"></i>
                        </button>

                        <!-- Input for Adding New Tag -->
                        <div v-if="chat.showTagInput" class="mt-1 flex items-center">
                          <input v-model="newTag" type="text" placeholder="Enter tag"
                            class="px-2 py-1 border border-gray-300 rounded text-sm mr-2"
                            @keyup.enter="addTag(chat)" />
                          <button @click.stop="addTag(chat)" class="text-blue-500 text-xs" aria-label="Add Tag">
                            <i class="fas fa-plus-circle"></i>
                          </button>
                        </div>

                        <!-- Chat Type Section -->
                        <div v-if="chat.rel_type" class="flex items-center mt-1" style="font-size: 0.875rem;">
                          <span v-if="chat.rel_type === 'leads'">
                            <a :href="chat.type_url" :onclick="'init_lead(' + chat.rel_id + ');return false;'" style="background-color: #EDF2F7; color: #4A5568; border-radius: 0.25rem; padding: 0.25rem 0.5rem; font-size: 0.75rem;" aria-label="'Lead ' + chat.type_id">
                              Lead {{ chat.rel_id }}
                            </a>
                            <span v-if="chat.lead_status_name" class="ml-2" :style="{'background-color': '#EDF2F7', 'color': '#4A5568', 'border-radius': '0.25rem', 'padding': '0.25rem 0.5rem', 'font-size': '0.75rem'}">
                              {{ chat.lead_status_name }}
                            </span>
                          </span>
                          <span v-else class="ml-2" :style="{'background-color': '#EDF2F7', 'color': '#4A5568', 'border-radius': '0.25rem', 'padding': '0.25rem 0.5rem', 'font-size': '0.75rem'}" aria-label="Chat relationship type">{{ chat.rel_type }}</span>
                        </div>


                        <!-- Pin/Unpin Button -->
                        <button @click.stop="togglePin(chat.id)"
                          :class="{'text-yellow-500': chat.is_pinned === '1', 'text-gray-500': chat.is_pinned === '0'}"
                          class="px-2 py-1 rounded transition-transform duration-200 transform hover:scale-110 ml-2"
                          aria-label="Pin Chat">
                          <i :class="chat.is_pinned === '1' ? 'fas fa-thumbtack' : 'fas fa-thumbtack'"></i>
                        </button>
                        <!-- Delete Button -->
                        <button @click.stop="deleteChat(chat.id)"
                          class="bg-red-500 text-white px-2 py-1 rounded transition-transform duration-200 transform hover:scale-110 ml-2"
                          aria-label="Delete Chat">
                          <i class="fas fa-trash-alt"></i>
                        </button>
                      </div>
                    </div>
                  </div>
                </li>
              </transition-group>
            </ul>
          </section>



        </aside>
        <!-- Main Content Area -->
        <main :class="isSidebarOpen ? 'col-span-3' : 'col-span-5'" class="bg-gray-200 flex flex-col transition-all duration-300 chat-background  h-90vh">
<header class="bg-gray-200 border-b border-gray-300 p-3 flex items-center justify-between" style="background: linear-gradient(360deg, #f0f4f8, #fff) !important;">
  <div class="flex items-center">
    <button @click="isSidebarOpen = !isSidebarOpen" class="icon is-only-mobile u-margin-end">
      <span v-if="isSidebarOpen" class="icon icon-menu">☰</span>
      <span v-else class="icon icon-menu">☰</span>
    </button>
    <div v-if="whatsapp_selectedinteraction" class="avatar-wrapper ml-3">
      <div class="avatar">
        {{ whatsapp_getAvatarInitials(whatsapp_selectedinteraction.name || 'N/A') }}
      </div>
      <div class="ml-3">
        <h1 class="font-bold text-lg text-gray-900">{{ whatsapp_selectedinteraction.name }}</h1>
        <span class="text-sm text-gray-500" v-if="whatsapp_selectedinteraction.receiver_id">
          {{ whatsapp_selectedinteraction.receiver_id }}
        </span><br />
        <span class="text-sm text-gray-500" v-if="whatsapp_selectedinteraction.last_msg_time">
          {{ whatsapp_alertTime(whatsapp_selectedinteraction.last_msg_time) }}
        </span>
      </div>
    </div>
  </div>
  <!-- Right side container for tags and header icons -->
  <div class="flex items-center ml-auto">
    <ul v-if="whatsapp_selectedinteraction && whatsapp_selectedinteraction.tags" class="flex gap-1 flex-wrap items-center">
      <li v-for="tag in getTagsArray(whatsapp_selectedinteraction.tags)" :key="tag" class="bg-gray-200 text-gray-800 rounded-full px-2 py-1 text-xs flex items-center">
        {{ tag }}
        <button @click.stop="removeTag(whatsapp_selectedinteraction, tag)" class="ml-1 text-red-500" aria-label="Remove tag">
          &times;
        </button>
      </li>
    </ul>
    <div class="header-icons flex items-center ml-3">
      <i class="fas fa-paperclip" @click="toggleAttachmentCard"></i>
    </div>
  </div>
</header>

          <div class="overflow-y-auto flex-grow p-4" :style="{ backgroundImage: 'url(' + whatsapp_chatbg + ')' }" ref="whatsapp_chatContainer">
            <div v-if="whatsapp_selectedinteraction && whatsapp_selectedinteractionMessages">
              <!-- Load More Button -->
              <div v-if="hasMoreMessages" class="flex justify-center mt-4">
                <button @click="loadMoreMessages" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-700">
                  Load More
                </button>
              </div>
              <ol class="space-y-4">
                <template v-for="(message, index) in whatsapp_selectedinteractionMessages" :key="index">
                  <li v-if="whatsapp_shouldShowDate(message, whatsapp_selectedinteractionMessages[index - 1])" class="flex justify-center items-center my-4">
                    <p class="inline-block p-2 bg-gray-200 text-gray-700 rounded-full text-xs shadow-md">{{ getDate(message.time_sent) }}</p>
                  </li>
                  <li :class="[
                                        'flex items-start mb-4 transition-transform transform duration-300 ease-in-out',
                                        { 'flex-row-reverse': message.nature === 'sent' }
                                    ]">
                    <div :class="[
                                            'relative max-w-[60%] p-3 rounded-lg shadow-sm',
                                            message.nature === 'sent' ? 'bg-whatsapp-sent' : 'bg-whatsapp-received text-black'
                                        ]"
                      v-bind="message.nature === 'sent' ? {
                                            'data-toggle': 'tooltip',
                                            'data-title': message.staff_name,
                                            'data-original-title': message.staff_name,
                                            'title': message.staff_name,
                                            'data-placement': 'left'
                                        } : {}" style="max-width: 60%; font-family: Helvetica, Arial, sans-serif;">

                      <!-- Show replies -->
                      <template v-if="message.ref_message_id">
                        <div class="bg-gray-100 p-2 mt-2 rounded-lg border border-gray-300 shadow-inner" v-if="getOriginalMessage(message.ref_message_id).message">
                          <p class="text-xs text-gray-600">Replying to:</p>
                          <p class="text-sm font-medium" v-html="getOriginalMessage(message.ref_message_id).message">{{ getOriginalMessage(message.ref_message_id).message }}</p>
                        </div>
                        <p class="text-sm font-normal" v-html="message.message" style="font-size: 15px; line-height: 1.4;">{{ message.message }}</p>
                      </template>

                      <!-- Text Message -->
                      <template v-else-if="message.type === 'text'">
                        <p class="text-sm" v-html="message.message">{{ message.message }}</p>
                      </template>

                      <!-- Image Message -->
                      <template v-else-if="message.type === 'image'">
                        <a :href="message.asset_url" data-lightbox="image-group">
                          <img :src="message.asset_url" alt="Image" class="rounded-lg shadow-md max-w-[300px] max-h-[300px]" style="border-radius: 10px;">
                        </a>
                        <p class="text-sm mt-2" v-if="message.caption" style="font-size: 15px; line-height: 1.4;">{{ message.caption }}</p>
                      </template>

                      <!-- Video Message -->
                      <template v-else-if="message.type === 'video'">
                        <video :src="message.asset_url" controls class="rounded-lg shadow-md max-w-[300px] max-h-[300px]" style="border-radius: 10px;"></video>
                        <p class="text-sm mt-2" v-if="message.message" style="font-size: 15px; line-height: 1.4;">{{ message.message }}</p>
                      </template>

                      <!-- Document Message -->
                      <template v-else-if="message.type === 'document'">
                        <a :href="message.asset_url" target="_blank" class="text-blue-500 hover:text-blue-700 underline" style="font-size: 15px; line-height: 1.4;">Download Document</a>
                      </template>

                      <!-- Audio Message -->
                      <template v-else-if="message.type === 'audio'">
                        <audio controls class="max-w-[300px]">
                          <source :src="message.asset_url" type="audio/mpeg">
                        </audio>
                      </template>

                      <!-- Carousel Message -->
                      <template v-else-if="message.type === 'carousel'">
                        <div class="carousel">
                          <div v-for="(item, idx) in message.carousel_items" :key="idx" class="carousel-item rounded-lg shadow-md p-2">
                            <img :src="item.image_url" alt="Carousel Image" class="rounded-lg max-w-[300px] max-h-[300px]">
                            <p class="text-sm mt-2 font-semibold" style="font-size: 15px; line-height: 1.4;">{{ item.title }}</p>
                            <p class="text-sm mt-1" style="font-size: 15px; line-height: 1.4;">{{ item.subtitle }}</p>
                            <a :href="item.button_url" target="_blank" class="text-blue-500 hover:text-blue-700 underline">{{ item.button_text }}</a>
                          </div>
                        </div>
                      </template>

                      <!-- Sticker Message -->
                      <template v-else-if="message.type === 'sticker'">
                        <img :src="message.asset_url" alt="Sticker" class="rounded-lg max-w-[300px] max-h-[300px] shadow-md">
                      </template>


                      <!-- Location Message -->
                      <template v-else-if="message.type === 'location'">
                        <p class="text-sm font-semibold" style="font-size: 15px; line-height: 1.4;">Location: {{ message.location_name }}</p>
                        <p class="text-sm" style="font-size: 15px; line-height: 1.4;">Address: {{ message.location_address }}</p>
                      </template>

                      <!-- Contact Message -->
                      <template v-else-if="message.type === 'contacts'">
                        <div v-for="(contact, idx) in message.contacts" :key="idx" class="p-2 border rounded-lg bg-gray-100">
                          <p class="text-sm font-bold" style="font-size: 15px; line-height: 1.4;">{{ contact.name.formatted_name }}</p>
                          <p class="text-sm" style="font-size: 15px; line-height: 1.4;">{{ contact.phones[0].phone }}</p>
                          <p class="text-sm" v-if="contact.emails[0].email" style="font-size: 15px; line-height: 1.4;">{{ contact.emails[0].email }}</p>
                        </div>
                      </template>

                      <!-- Poll Message -->
                      <template v-else-if="message.type === 'poll'">
                        <p class="text-sm font-bold" style="font-size: 15px; line-height: 1.4;">{{ message.poll_question }}</p>
                        <ul class="mt-2 space-y-1">
                          <li v-for="(option, idx) in message.poll_options" :key="idx" class="text-sm" style="font-size: 15px; line-height: 1.4;">
                            <span class="font-semibold">{{ option }}:</span> {{ message.poll_results[idx] }} votes
                          </li>
                        </ul>
                      </template>

                      <!-- Template Message -->
                      <template v-else-if="message.type === 'template'">
                        <p class="text-sm font-normal" v-html="message.message" style="font-size: 15px; line-height: 1.4;">{{ message.message }}</p>
                      </template>
                      <!-- Order Message -->
                      <template v-else-if="message.type === 'order'">
                        <div class="bg-green-100 p-3 rounded-lg border border-green-300 shadow-inner">
                          <p class="text-sm font-normal" v-html="message.message" style="font-size: 15px; line-height: 1.4;">{{ message.message }}</p>

                        </div>
                      </template>

                      <template v-else-if="message.type === 'request_welcome'||message.message === 'request_welcome'">
                        <div class="bg-blue-100 p-3 rounded-lg border border-blue-300 shadow-inner">
                          <p class="text-sm text-blue-700" style="font-size: 15px; line-height: 1.4;">{{ message.message }}</p>
                        </div>
                      </template>

                      <!-- Message Status and Actions -->
                      <div class="flex justify-between items-center mt-2 text-xs text-gray-500">
                        <span>{{ whatsapp_getTime(message.time_sent) }}</span>

                        <span v-if="message.nature === 'sent'" class="ml-2 flex items-center">
                          <!-- Display status icons based on message status -->
                          <i v-if="message.status === 'sent'" class="fa fa-check" title="Sent"></i>
                          <i v-else-if="message.status === 'delivered'" class="fa fa-check-double" title="Delivered"></i>
                          <i v-else-if="message.status === 'read'" class="fa fa-check-double text-blue-500" title="Read"></i>
                          <i v-else-if="message.status === 'failed'" class="fa fa-exclamation-triangle text-red-500" :title="message.status_message"></i>
                          <i v-else-if="message.status === 'accepted'" class="fa fa-check-circle text-green-500" title="Accepted"></i>
                        </span>

                        <span class="ml-auto flex items-center">
                          <i class="fas fa-reply cursor-pointer" @click="replyToMessage(message)" title="Reply"></i>
                        </span>
                      </div>

                      <!-- Red Alert for Failed Messages -->
                      <div v-if="message.status === 'failed'" class="mt-2 text-xs text-red-500 bg-red-100 p-2 rounded">
                        <span>❌ ERROR from Meta: <br /><span v-html="message.status_message"></span></span>
                      </div>

                    </div>
                  </li>
                </template>
              </ol>
            </div>
          </div>
          <!-- Message Form -->
          <form v-on:submit.prevent="whatsapp_sendMessage" v-if="whatsapp_selectedinteraction && whatsapp_selectedinteractionMessages" class="bg-gray-200 p-5 border-t border-gray-300 relative flex flex-col" style="background: linear-gradient(360deg, rgb(240, 244, 248), rgb(255, 255, 255)) !important;">
            <!-- Expired Interaction Alert -->
            <div v-if="whatsapp_selectedinteraction.status === 'expired'" class="alert alert-warning">
              The customer service window has expired. You can no longer send regular messages to the user. To continue communication, you can only send template messages.Supported Template messages are the only type allowed from this window.
            </div>

            <!-- Replying To Message Section -->
            <div v-if="replyingToMessage" class="flex items-center mb-2 bg-gray-100 p-2 rounded-lg">
              <div class="flex-grow">
                <p class="text-xs text-gray-600">Replying to:</p>
                <p class="text-sm" v-html="replyingToMessage.message"></p>
              </div>
              <button @click="clearReply" class="text-xs text-red-500 ml-2">Cancel</button>
            </div>

            <!-- Quick Replies Section -->
            <ul v-if="showQuickReplies" class="flex-grow bg-white shadow-md rounded-lg mt-2 p-2">
              <li v-for="(reply, index) in filteredQuickReplies"
                :key="index"
                @click="selectQuickReply(index)"
                :class="{
                                        'bg-blue-100 text-blue-900': index === quickReplyIndex,
                                        'hover:bg-gray-100 cursor-pointer rounded-md p-2 transition-all duration-200 ease-in-out': true
                                    }">
                {{ reply.message }}
              </li>
            </ul>

            <div class="flex items-center w-full">
              <!-- Emoji Picker Section -->
              <span class="relative" v-if="whatsapp_selectedinteraction && whatsapp_selectedinteraction.status === 'active'">
                <i class="fa fa-smile cursor-pointer mx-2 text-gray-500 hover:text-gray-700" @click="toggleEmojiPicker"></i>
                <div class="emoji-picker absolute bottom-12 left-0" :class="{ 'show': showEmojiPicker }">
                  <div class="emoji-categories">
                    <button v-for="category in emojiCategories" @click="selectedCategory = category" :class="{ 'active': selectedCategory === category }">
                      {{ category }}
                    </button>
                  </div>
                  <ul>
                    <li v-for="emoji in emojis[selectedCategory]" @click="addEmoji(emoji)">{{ emoji }}</li>
                  </ul>
                </div>
              </span>

              <!-- Message Input Section -->
              <textarea
                v-model="whatsapp_newMessage"
                @input="handleInput"
                @keydown.down.prevent="navigateQuickReplies(1)"
                @keydown.up.prevent="navigateQuickReplies(-1)"
                @keydown.enter="handleEnterKey($event)"
                class="form-control flex-grow border-none p-2 rounded-full mx-2 w-75"
                placeholder="Type your message..."
                id="whatsapp_newMessage"
                style="border: none; padding: 10px; margin: 0 10px; resize: none;"
                v-if="whatsapp_selectedinteraction && whatsapp_selectedinteraction.status === 'active'"></textarea>

              <!-- Template Selection Dropdown -->
              <select v-model="selectedTemplateId" class="form-control w-60">
                <option value="">Select a Template</option>
                <option v-for="template in templatesWithoutParameters" :key="template.id" :value="template.id">
                  {{ template.template_name }}
                </option>
              </select>

              <!-- AI Response Button -->
              <?php if (get_option('whatsapp_openai_token') && get_option('whatsapp_openai_status') === "manual"): ?>
                <button @click="generateAIResponse" type="button" class="btn btn-default mx-1" v-if="whatsapp_selectedinteraction && whatsapp_selectedinteraction.status === 'active'">
                  <i class="fa fa-robot text-gray-500 hover:text-gray-700" data-toggle="tooltip" data-title="AI Response" data-placement="top" title="AI Response"></i>
                </button>
              <?php endif; ?>

              <!-- Attachment and Recording Section -->
              <span class="flex items-center relative">
                <i class="fa fa-paperclip cursor-pointer mx-2 text-gray-500 hover:text-gray-700" @click="toggleAttachmentCard" v-if="whatsapp_selectedinteraction && whatsapp_selectedinteraction.status === 'active'"></i>
                <div class="attachment-card absolute bottom-12 right-0" :class="{ 'show': showAttachmentCard }">
                  <ul>
                    <li @click="triggerFileInput('image')">
                      <i class="fa fa-image text-blue-500"></i> Image
                    </li>
                    <li @click="triggerFileInput('video')">
                      <i class="fa fa-video text-green-500"></i> Video
                    </li>
                    <li @click="triggerFileInput('document')">
                      <i class="fa fa-file text-orange-500"></i> Document
                    </li>
                  </ul>
                </div>
                <input type="file" ref="imageAttachmentInput" style="display: none;" @change="whatsapp_handleImageAttachmentChange" accept="image/*">
                <input type="file" ref="videoAttachmentInput" style="display: none;" @change="whatsapp_handleVideoAttachmentChange" accept="video/*">
                <input type="file" ref="documentAttachmentInput" style="display: none;" @change="whatsapp_handleDocumentAttachmentChange" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.rtf">
                <button v-on:click="whatsapp_toggleRecording" type="button" class="btn btn-default mx-1" v-if="whatsapp_selectedinteraction && whatsapp_selectedinteraction.status === 'active'">
                  <span v-if="!whatsapp_recording" class="fa fa-microphone text-gray-500 hover:text-gray-700" data-toggle="tooltip" data-title="Record Audio" data-placement="top" title=""></span>
                  <span v-else class="fas fa-stop rounded-full p-2 text-red-500 hover:text-red-700"></span>
                </button>
                <button v-if="whatsapp_showSendButton" type="submit" class="btn btn-default mx-1">
                  <i class="fa fa-paper-plane text-gray-500 hover:text-gray-700"></i>
                </button>
              </span>
            </div>
          </form>
        </main>
      </section>
      <!-- Modal Structure -->
      <div v-if="isModalOpen" class="fixed inset-0 flex items-center justify-center z-50">
        <div class="absolute inset-0 bg-black opacity-50" @click="closeModal"></div>
        <div class="bg-white rounded-lg shadow-xl max-w-lg w-full p-6 z-10 transform transition-all duration-300">
          <h3 class="text-xl font-bold mb-4 flex items-center">
            <i class="fas fa-comments mr-2"></i> Initiate Conversation
          </h3>
          <form @submit.prevent="submitForm">
            <!-- From Number Select Input -->
            <label class="block mb-2 text-sm font-medium text-gray-700">From</label>
            <select v-model="selectedFromNumber"
              class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-400 mb-4">
              <option value="">Select Sender Number</option>
              <option v-for="number in whatsapp_uniqueWaNos"
                :key="number.phone_number_id"
                :value="number.phone_number_id">
                {{ number.phone_number }}
              </option>
            </select>

            <!-- Recipient Phone Number Input -->
            <input type="text" v-model="phoneNumber"
              class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-400 mb-4"
              placeholder="Enter Phone Number">

            <!-- Template Selection Dropdown -->
            <select v-model="templateId"
              class="w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-400 mb-4">
              <option value="">Select a Template</option>
              <option v-for="template in templatesWithoutParameters"
                :key="template.id"
                :value="template.id">
                {{ template.template_name }}
              </option>
            </select>

            <!-- Action Buttons -->
            <div class="mt-4 flex justify-end">
              <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                Send
              </button>
              <button type="button" @click="closeModal"
                class="ml-3 px-4 py-2 border rounded-md hover:bg-gray-200">
                Cancel
              </button>
            </div>
          </form>
        </div>
      </div>



    </div>
  </div>
</div>
<?php init_tail(); ?>
<script src="<?php echo module_dir_url(WHATSAPP_MODULE, 'assets/js/chat.js'); ?>"></script>
<script src="<?php echo module_dir_url(WHATSAPP_MODULE, 'assets/js/vue.min.js'); ?>"></script>
<script src="<?php echo module_dir_url(WHATSAPP_MODULE, 'assets/js/axios.min.js'); ?>"></script>
<script src="<?php echo module_dir_url(WHATSAPP_MODULE, 'assets/js/recorder-core.js'); ?>"></script>
<script src="<?php echo module_dir_url(WHATSAPP_MODULE, 'assets/js/purify.min.js'); ?>"></script>
<script src="<?php echo module_dir_url(WHATSAPP_MODULE, 'assets/js/mp3-engine.js'); ?>"></script>
<script src="<?php echo module_dir_url(WHATSAPP_MODULE, 'assets/js/mp3.js'); ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script>
  "use strict";
  new Vue({
    el: '#app',
    data() {
      return {
        interactions: [],
        limit: <?php echo get_option('whatsapp_load_more_limit') ?: 20; ?>, // Number of interactions to load per request
        loading: false,
        reloading: false,
        messageLimit: 20,
        hasMoreMessages: false,
        reloadingtime: <?php echo get_option('whatsapp_reload_interval') ?: 5; ?>,
        hasMoreData: true, // Track if there are more pages to load
        isIntervalPaused: false, // Flag to track if the interval is paused
        whatsapp_selectedinteractionIndex: null,
        whatsapp_selectedinteraction: null,
        whatsapp_selectedinteractionMobNo: null,
        whatsapp_selectedinteractionID: null,
        whatsapp_selectedinteractionSenderNo: null,
        whatsapp_selectedinteractionMessages: null,
        whatsapp_newMessage: '',
        whatsapp_imageAttachment: null,
        whatsapp_videoAttachment: null,
        whatsapp_documentAttachment: null,
        whatsapp_imagePreview: '',
        whatsapp_videoPreview: '',
        whatsapp_csrfToken: '<?php echo $csrfToken; ?>',
        whatsapp_recording: false,
        whatsapp_audioBlob: null,
        whatsapp_recordedAudio: null,
        errorMessage: '',
        whatsapp_searchText: '',
        whatsapp_selectedWaNo: '*',
        whatsapp_selectedInteractionType: '*',
        whatsapp_selectedStatus: '*',
        whatsapp_selectedAssigned: '*',
        whatsapp_selectedUnread: '*', // New property for unread filter
        whatsapp_filteredInteractions: [],
        whatsapp_displayedInteractions: [],
        whatsapp_profilePictureUrl: null,
        whatsapp_uniqueWaNos: <?php echo json_encode($numbers); ?>,
        whatsapp_templates: <?php echo json_encode(get_whatsapp_template()); ?>,
        whatsapp_uniqueInteractionTypes: ["staff", "leads", "contacts", "customers", "general"],
        whatsapp_uniqueStatuses: <?php echo json_encode($statuses); ?>,
        whatsapp_uniqueStaff: <?php echo json_encode($staffs); ?>,
        whatsapp_quickreplies: <?php echo json_encode($quick_replies); ?>,
        isSidebarOpen: true,
        whatsapp_selectedProfilePicture: '<?php echo module_dir_url(WHATSAPP_MODULE, 'assets/images/icon.png'); ?>',
        whatsapp_chatbg: '<?php echo module_dir_url(WHATSAPP_MODULE, 'assets/images/bg.jpg'); ?>',
        whatsapp_selectedProfileName: '',
        showQuickReplies: false,
        filteredQuickReplies: [],
        showEmojiPicker: false,
        quickReplyIndex: -1,
        showAttachmentCard: false,
        emojis: null,
        emojiCategories: [],
        selectedCategory: 'Smileys',
        replyingToMessage: null,
        newTag: '',
        tagOperationMessage: 'this.',
        selectedTemplateId: '', // Holds the selected template ID
        templateParameters: [], // Parameters for the template
        isModalOpen: false,
        phoneNumber: '',
        templateId: '',
        selectedFromNumber: '', // New property for the "From" number
        filterTimeout: null,
      };
    },
    methods: {
async whatsapp_selectinteraction(id, reloading = false) {
  try {
    const selectedInteraction = this.interactions.find(interaction => interaction.id === id);
    this.whatsapp_selectedinteractionID = id;

    if (!selectedInteraction) {
      console.error('Interaction not found.');
      return;
    }

    this.whatsapp_selectedinteraction = selectedInteraction;
    this.whatsapp_selectedinteractionMobNo = selectedInteraction.receiver_id;
    this.whatsapp_selectedinteractionSenderNo = selectedInteraction.wa_no;

    // 🚀 Increase limit if loading more
    if (reloading) {
      this.messageLimit += <?php echo get_option('whatsapp_load_more_limit') ?: 20; ?>;
    }

    const response = await $.ajax({
      url: `${admin_url}whatsapp/get_interaction_messages`,
      type: 'GET',
      dataType: 'json',
      data: {
        csrf_token_name: this.whatsapp_csrfToken,
        interaction_id: id,
        offset: 0,
        limit: this.messageLimit
      },
    });

    const newFetchedMessages = response.messages?.messages || [];

    const currentMessagesStr = JSON.stringify(this.whatsapp_selectedinteractionMessages || []);
    const newMessagesStr = JSON.stringify(newFetchedMessages || []);

    if (newMessagesStr !== currentMessagesStr) {
      this.whatsapp_selectedinteractionMessages = newFetchedMessages;

      if (!reloading) {
        this.whatsapp_scrollToBottom();
      }

    } else {
      console.log("✅ No message change detected. Skipping UI update.");
    }

    this.hasMoreMessages = response.messages?.hasMoreMessages;

    if (selectedInteraction.unread > 0) {
      await $.post(`${admin_url}whatsapp/chat_mark_as_read`, {
        interaction_id: id
      });
    }

  } catch (error) {
    console.error('Error processing the interaction:', error);
  }
},
async loadMoreMessages() {
  if (!this.hasMoreMessages) return;

  // Increase the limit by the defined option (e.g., 20)
  this.messageLimit += <?php echo get_option('whatsapp_load_more_limit') ?: 20; ?>;

  // Reload messages with new limit
  await this.whatsapp_selectinteraction(this.whatsapp_selectedinteraction.id, true);
},
async whatsapp_sendMessage() {
        if (!this.whatsapp_selectedinteraction || !this.whatsapp_selectedinteraction.id || !this.whatsapp_selectedinteraction.receiver_id) {
          console.error('Selected interaction is not properly initialized.');
          return;
        }
        const whatsapp_formData = new FormData();
        whatsapp_formData.append('id', this.whatsapp_selectedinteraction.id);
        whatsapp_formData.append('to', this.whatsapp_selectedinteraction.receiver_id);
        whatsapp_formData.append('csrf_token_name', this.whatsapp_csrfToken);

        if (this.selectedTemplateId) {
          whatsapp_formData.append('template_id', this.selectedTemplateId);
          const template = this.whatsapp_templates.find(t => t.id === this.selectedTemplateId);
          if (!template) {
            console.error('Template not found.');
            return;
          }
          const headerParamsCount = template.header_params_count || 0;
          const bodyParamsCount = template.body_params_count || 0;
          const footerParamsCount = template.footer_params_count || 0;
          if (
            (this.templateParameters.header && this.templateParameters.header.length !== headerParamsCount) ||
            (this.templateParameters.body && this.templateParameters.body.length !== bodyParamsCount) ||
            (this.templateParameters.footer && this.templateParameters.footer.length !== footerParamsCount)
          ) {
            console.error(`Parameter count mismatch: Expected header ${headerParamsCount}, body ${bodyParamsCount}, footer ${footerParamsCount}`);
            return;
          }
          this.templateParameters.forEach((param, index) => {
            whatsapp_formData.append(`parameters[${index}]`, param);
          });
        } else {
          const MAX_MESSAGE_LENGTH = 2000;
          if (this.whatsapp_newMessage.length > MAX_MESSAGE_LENGTH) {
            this.whatsapp_newMessage = this.whatsapp_newMessage.substring(0, MAX_MESSAGE_LENGTH);
          }
          if (this.whatsapp_newMessage.trim()) {
            whatsapp_formData.append('message', DOMPurify.sanitize(this.whatsapp_newMessage));
          }
          if (this.whatsapp_imageAttachment) {
            whatsapp_formData.append('image', this.whatsapp_imageAttachment);
          }
          if (this.whatsapp_videoAttachment) {
            whatsapp_formData.append('video', this.whatsapp_videoAttachment);
          }
          if (this.whatsapp_documentAttachment) {
            whatsapp_formData.append('document', this.whatsapp_documentAttachment);
          }
          if (this.whatsapp_audioBlob) {
            whatsapp_formData.append('audio', this.whatsapp_audioBlob, 'audio.mp3');
          }
        }
        if (this.replyingToMessage) {
          whatsapp_formData.append('ref_message_id', this.replyingToMessage.message_id);
        }
        try {
          await axios.post('<?php echo admin_url('whatsapp/webhook/send_message'); ?>', whatsapp_formData, {
            headers: {
              'Content-Type': 'multipart/form-data'
            }
          });
          this.whatsapp_clearAttachments();
          this.whatsapp_filterInteractions();
          this.whatsapp_selectinteraction(this.whatsapp_selectedinteraction.id);
          this.errorMessage = '';
          this.whatsapp_scrollToBottom();
          this.replyingToMessage = null;
          this.selectedTemplateId = '';
          this.templateParameters = [];
        } catch (error) {
          this.handleErrorResponse(error);
        }
      },
      whatsapp_clearMessage() {
        this.whatsapp_newMessage = '';
        this.whatsapp_clearAttachments();
      },
      whatsapp_handleAttachmentChange(event) {
        const files = event.target.files;
        this.attachment = files[0];
      },
      async whatsapp_fetchInteractions(loading = false) {
        if (this.loading) return;
        this.loading = true;
        try {
          // Pass all filter values to the server as query parameters.
          const response = await axios.get('<?php echo admin_url('whatsapp/interactions'); ?>', {
            params: {
              wa_no_id: this.whatsapp_selectedWaNo,
              interaction_type: this.whatsapp_selectedInteractionType,
              status_id: this.whatsapp_selectedStatus,
              assigned_staff_id: this.whatsapp_selectedAssigned,
              status: this.whatsapp_selectedUnread,
              searchtext: this.whatsapp_searchText,
            }
          });
          const data = response.data;

          // Convert current interactions and new interactions to JSON strings.
          const currentDataStr = JSON.stringify(this.interactions);
          const newDataStr = JSON.stringify(data.interactions);

          // Update only if there is a significant change.
          if (newDataStr !== currentDataStr) {
            if (data.interactions && data.interactions.length > 0) {
              // Map over each interaction to ensure it has a showTagInput property.
              this.interactions = data.interactions.map(chat => ({
                ...chat,
                showTagInput: typeof chat.showTagInput !== 'undefined' ? chat.showTagInput : false
              }));
              // Directly assign the interactions for display.
              this.whatsapp_displayedInteractions = this.interactions;
            } else {
              // If no records returned, set arrays to empty.
              this.interactions = [];
              this.whatsapp_displayedInteractions = [];
            }
          } else {
            console.log("No significant changes in interactions. UI update skipped.");
          }
        } catch (error) {
          console.error('Error fetching interactions:', error);
        } finally {
          this.loading = false;
        }
      },
      whatsapp_updateSelectedInteraction() {
        if (Array.isArray(this.interactions)) {
          const index = this.interactions.findIndex(interaction =>
            interaction.receiver_id === this.whatsapp_selectedinteractionMobNo &&
            interaction.wa_no === this.whatsapp_selectedinteractionSenderNo
          );
          if (index !== -1) {
            this.whatsapp_selectedinteraction = this.interactions[index];
          }
        }
      },
      whatsapp_getTime(timeString) {
        return timeString ? timeString.split(' ')[1] : '';
      },
      getDate(dateString) {
        const dt = new Date(dateString);
        return dt.toLocaleDateString('en-GB', {
          day: 'numeric',
          month: 'long',
          year: 'numeric'
        }).replace(/ /g, '-');
      },
      whatsapp_shouldShowDate(currentMessage, previousMessage) {
        if (!previousMessage) return true;
        return this.getDate(currentMessage.time_sent) !== this.getDate(previousMessage.time_sent);
      },
      whatsapp_scrollToBottom() {
        this.$nextTick(() => {
          const container = this.$refs.whatsapp_chatContainer;
          if (container) {
            container.scrollTop = container.scrollHeight;
          }
        });
      },
      whatsapp_getAvatarInitials(name) {
        return name.split(' ').slice(0, 2).map(word => word.charAt(0)).join('').toUpperCase();
      },
      async whatsapp_toggleRecording() {
        if (!this.whatsapp_recording) {
          this.whatsapp_startRecording();
        } else {
          this.whatsapp_stopRecording();
        }
      },
      toggleEmojiPicker() {
        this.showEmojiPicker = !this.showEmojiPicker;
      },
      addEmoji(emoji) {
        this.whatsapp_newMessage += emoji;
      },
      toggleAttachmentCard() {
        this.showAttachmentCard = !this.showAttachmentCard;
      },
      triggerFileInput(type) {
        const inputRef = this.$refs[`${type}AttachmentInput`];
        if (inputRef) {
          inputRef.click();
          this.showAttachmentCard = false;
        } else {
          console.error(`Element with ref ${type}AttachmentInput not found`);
        }
      },
      whatsapp_startRecording() {
        if (!this.recorder) {
          this.recorder = new Recorder({
            type: "mp3",
            sampleRate: 16000,
            bitRate: 16
          });
        }
        this.recorder.open(() => {
          this.whatsapp_recording = true;
          this.recorder.start();
        }, err => {
          console.error("Failed to start recording:", err);
        });
      },
      whatsapp_stopRecording() {
        if (this.recorder && this.whatsapp_recording) {
          this.recorder.stop((blob) => {
            this.recorder.close();
            this.whatsapp_recording = false;
            this.whatsapp_audioBlob = blob;
            this.whatsapp_sendMessage();
            this.whatsapp_recordedAudio = URL.createObjectURL(blob);
          }, err => {
            console.error("Failed to stop recording:", err);
          });
        }
      },
      whatsapp_handleImageAttachmentChange(event) {
        this.whatsapp_imageAttachment = event.target.files[0];
        this.whatsapp_imagePreview = URL.createObjectURL(this.whatsapp_imageAttachment);
      },
      whatsapp_handleVideoAttachmentChange(event) {
        this.whatsapp_videoAttachment = event.target.files[0];
        this.whatsapp_videoPreview = URL.createObjectURL(this.whatsapp_videoAttachment);
      },
      whatsapp_handleDocumentAttachmentChange(event) {
        this.whatsapp_documentAttachment = event.target.files[0];
      },
      whatsapp_removeImageAttachment() {
        this.whatsapp_imageAttachment = null;
        this.whatsapp_imagePreview = '';
      },
      whatsapp_removeVideoAttachment() {
        this.whatsapp_videoAttachment = null;
        this.whatsapp_videoPreview = '';
      },
      whatsapp_removeDocumentAttachment() {
        this.whatsapp_documentAttachment = null;
      },
      whatsapp_formatTime(timestamp) {
        const currentDate = new Date();
        const messageDate = new Date(timestamp);
        const diffInHours = (currentDate - messageDate) / (1000 * 60 * 60);
        if (diffInHours < 24) {
          const hour = messageDate.getHours();
          const minute = messageDate.getMinutes();
          const period = hour < 12 ? 'AM' : 'PM';
          return `${hour % 12 || 12}:${minute < 10 ? '0' + minute : minute} ${period}`;
        } else {
          return `${messageDate.getDate()}-${messageDate.getMonth() + 1}-${messageDate.getFullYear() % 100}`;
        }
      },
      whatsapp_alertTime(lastMsgTime) {
        if (!lastMsgTime) return '';
        const currentDate = new Date();
        const messageDate = new Date(lastMsgTime);
        const diffInMs = currentDate - messageDate;
        const diffInHours = Math.floor(diffInMs / (1000 * 60 * 60));
        const diffInMinutes = Math.floor((diffInMs % (1000 * 60 * 60)) / (1000 * 60));
        if (diffInHours < 24) {
          const remainingHours = 23 - diffInHours;
          const remainingMinutes = 60 - diffInMinutes;
          return `Reply within ${remainingHours} hours and ${remainingMinutes} minutes`;
        }
        return '';
      },
      whatsapp_filterInteractions() {
        // Start with the full interactions array
        let filtered = this.interactions;

        // Filter by WhatsApp Number (if not set to '*' for all)
        if (this.whatsapp_selectedWaNo !== '*') {
          filtered = filtered.filter(interaction =>
            interaction.phone_number_id === this.whatsapp_selectedWaNo
          );
        }

        // Filter by Interaction Type (if not set to '*')
        if (this.whatsapp_selectedInteractionType !== '*') {
          filtered = filtered.filter(interaction =>
            interaction.interaction_type && interaction.interaction_type.toLowerCase() === this.whatsapp_selectedInteractionType.toLowerCase()
          );
        }

        // Filter by Status (if not set to '*')
        if (this.whatsapp_selectedStatus !== '*') {
          filtered = filtered.filter(interaction =>
            interaction.status && interaction.status.toLowerCase() === this.whatsapp_selectedStatus.toLowerCase()
          );
        }

        // Filter by Assigned Staff (if not set to '*')
        if (this.whatsapp_selectedAssigned !== '*') {
          filtered = filtered.filter(interaction =>
            interaction.assigned_staff_id && interaction.assigned_staff_id.toString() === this.whatsapp_selectedAssigned.toString()
          );
        }

        // Filter by Unread Status (if not set to '*')
        if (this.whatsapp_selectedUnread !== '*') {
          if (this.whatsapp_selectedUnread === 'unread') {
            filtered = filtered.filter(interaction =>
              parseInt(interaction.unread, 10) > 0
            );
          } else if (this.whatsapp_selectedUnread === 'active') {
            filtered = filtered.filter(interaction =>
              interaction.status && interaction.status.toLowerCase() === 'active'
            );
          } else if (this.whatsapp_selectedUnread === 'expired') {
            filtered = filtered.filter(interaction =>
              interaction.status && interaction.status.toLowerCase() === 'expired'
            );
          }
        }

        // Set the filtered interactions and apply the search filter
        this.whatsapp_filteredInteractions = filtered;
        this.whatsapp_searchInteractions();
      },

      whatsapp_searchInteractions() {
        if (this.whatsapp_searchText) {
          const searchText = this.whatsapp_searchText.toLowerCase();
          this.whatsapp_displayedInteractions = this.whatsapp_filteredInteractions.filter(interaction =>
            (interaction.name && interaction.name.toLowerCase().includes(searchText)) ||
            (interaction.tags && interaction.tags.toLowerCase().includes(searchText)) ||
            (interaction.receiver_id && interaction.receiver_id.toLowerCase().includes(searchText))
          );
        } else {
          this.whatsapp_displayedInteractions = this.whatsapp_filteredInteractions;
        }
      },
      handleEnterKey(event) {
        if (event.shiftKey) {
          // If Shift+Enter is pressed, allow a new line.
          return;
        }
        // Prevent default behavior and send the message if Enter is pressed without Shift.
        event.preventDefault();
        this.whatsapp_sendMessage();
      },
      whatsapp_markInteractionAsRead(interactionId) {
        const interaction = this.interactions.find(interaction => interaction.id === interactionId);
        if (interaction) {
          interaction.read = true;
        }
        fetch('<?php echo admin_url('whatsapp/webhook/mark_interaction_as_read'); ?>', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              interaction_id: interactionId,
              csrf_token_name: this.whatsapp_csrfToken
            }),
          })
          .then(whatsapp_response => {
            if (!whatsapp_response.ok) {
              throw new Error('Network response was not ok');
            }
            return whatsapp_response.json();
          })
          .catch(error => {
            console.error('Error marking interaction as read:', error);
            if (interaction) {
              interaction.read = false;
            }
          });
      },
      deleteChat(chatId) {
        if (!confirm("Are you sure you want to delete this chat?")) {
          return;
        }
        $.ajax({
          url: `${admin_url}whatsapp/delete_chat`,
          type: 'POST',
          dataType: 'json',
          data: {
            'chat_id': chatId,
            csrf_token_name: $('meta[name="csrf-token"]').attr('content')
          },
          success: (response) => {
            if (response.success) {
              this.whatsapp_displayedInteractions = this.whatsapp_displayedInteractions.filter(chat => chat.id !== chatId);
              alert('Chat deleted successfully.');
            } else {
              console.error('Failed to delete chat:', response.message);
              alert(`Error: ${response.message}`);
            }
          },
          error: (xhr, status, error) => {
            console.error('Error deleting chat:', error);
            alert('An error occurred while deleting the chat. Please try again.');
          }
        });
      },
      getTagsArray(tags) {
        return tags ? tags.split(',').map(tag => tag.trim()) : [];
      },
      saveTagsArray(tagsArray) {
        return tagsArray.join(',');
      },
      toggleTagInput(chat) {
        chat.showTagInput = !chat.showTagInput;
      },
      async addTag(chat) {
        const newTag = this.newTag.trim();
        if (newTag && !this.getTagsArray(chat.tags).includes(newTag)) {
          const updatedTagsArray = [...this.getTagsArray(chat.tags), newTag];
          chat.tags = this.saveTagsArray(updatedTagsArray);
          this.newTag = '';
          try {
            await $.post(`${admin_url}whatsapp/save_tag`, {
              interaction_id: chat.id,
              tag: newTag,
              csrf_token_name: $('meta[name="csrf-token"]').attr('content')
            });
          } catch (error) {
            console.error('Error saving tag:', error);
          }
        }
      },
      async removeTag(chat, tag) {
        const updatedTagsArray = this.getTagsArray(chat.tags).filter(t => t !== tag);
        chat.tags = this.saveTagsArray(updatedTagsArray);
        try {
          await $.post(`${admin_url}whatsapp/delete_tag`, {
            interaction_id: chat.id,
            tag: tag,
            csrf_token_name: $('meta[name="csrf-token"]').attr('content')
          });
        } catch (error) {
          console.error('Error deleting tag:', error);
        }
      },
      handleErrorResponse(error) {
        const raw = error.response && error.response.data ? error.response.data : 'An error occurred. Please try again.';
        const typeMatch = raw.match(/<p>Type: (.+)<\/p>/);
        let messageMatch = raw.match(/<p>Message: (.+)<\/p>/);
        if (typeof(messageMatch[1]) === 'object') {
          messageMatch[1] = JSON.parse(messageMatch[1]);
          messageMatch[1] = messageMatch[1].error.message;
        }
        const typeText = typeMatch ? typeMatch[1] : '';
        const messageText = messageMatch ? messageMatch[1] : '';
        this.errorMessage = typeText.trim() + '\n' + messageText.trim();
      },
      whatsapp_clearAttachments() {
        this.whatsapp_newMessage = '';
        this.whatsapp_imageAttachment = null;
        this.whatsapp_videoAttachment = null;
        this.whatsapp_documentAttachment = null;
        this.whatsapp_audioBlob = null;
        this.whatsapp_imagePreview = '';
        this.whatsapp_videoPreview = '';
      },
      updateProfileData() {
        const selected = this.whatsapp_uniqueWaNos.find(interaction => interaction.phone_number_id === this.whatsapp_selectedWaNo);
        if (selected) {
          this.whatsapp_selectedProfilePicture = selected.profile_picture_url || '<?php echo module_dir_url(WHATSAPP_MODULE, 'assets/images/icon.png'); ?>';
          this.whatsapp_selectedProfileName = selected.phone_number;
        } else {
          this.whatsapp_selectedProfilePicture = '<?php echo module_dir_url(WHATSAPP_MODULE, 'assets/images/icon.png'); ?>';
          this.whatsapp_selectedProfileName = 'All Chats';
        }
      },
      handleInput() {
        if (this.whatsapp_newMessage.endsWith('/')) {
          this.showQuickReplies = true;
          this.filteredQuickReplies = this.whatsapp_quickreplies.filter(reply =>
            reply.message.toLowerCase().includes(this.whatsapp_newMessage.toLowerCase().slice(0, -1))
          );
          this.quickReplyIndex = -1;
        } else {
          this.showQuickReplies = false;
        }
      },
      navigateQuickReplies(direction) {
        if (!this.showQuickReplies) return;
        const totalReplies = this.filteredQuickReplies.length;
        this.quickReplyIndex = (this.quickReplyIndex + direction + totalReplies) % totalReplies;
      },
      selectQuickReply(index = this.quickReplyIndex) {
        if (index >= 0 && index < this.filteredQuickReplies.length) {
          const selectedReply = this.filteredQuickReplies[index].message;
          this.whatsapp_newMessage = selectedReply;
          this.showQuickReplies = false;
        }
      },
      async loadEmojis() {
        try {
          const response = await fetch('<?php echo module_dir_url(WHATSAPP_MODULE, 'assets/json/emojis.json'); ?>');
          const data = await response.json();
          if (data && typeof data === 'object') {
            this.emojiCategories = Object.keys(data);
            this.emojis = data;
            this.selectedCategory = this.emojiCategories[0];
          } else {
            throw new Error('Invalid emoji data');
          }
        } catch (error) {
          console.error('Error loading emojis:', error);
        }
      },
      getOriginalMessage(refMessageId) {
        if (Array.isArray(this.whatsapp_selectedinteraction?.messages)) {
          return this.whatsapp_selectedinteraction.messages.find(msg => msg.message_id === refMessageId) || {};
        }
        return {};
      },
      replyToMessage(message) {
        this.replyingToMessage = message;
      },
      clearReply() {
        this.replyingToMessage = null;
      },
      togglePin(chatId) {
        const chat = this.whatsapp_displayedInteractions.find(c => c.id === chatId);
        if (chat) {
          chat.is_pinned = chat.is_pinned === '1' ? '0' : '1';
          const data = {
            chat_id: chat.id,
            csrf_token_name: $('meta[name="csrf-token"]').attr('content')
          };
          const queryString = $.param(data);
          const url = `${admin_url}whatsapp/pinorunpin?${queryString}`;
          $.get(url)
            .done(function(response) {
              console.log('Pinned state updated successfully:', response);
            })
            .fail(function(jqXHR, textStatus, errorThrown) {
              console.error('Error updating pinned state:', textStatus, errorThrown);
              chat.is_pinned = chat.is_pinned === '1' ? '0' : '1';
            });
        }
      },
      async generateAIResponse() {
        try {
          const data = {
            interaction_id: this.whatsapp_selectedinteractionID,
            message: this.whatsapp_newMessage,
          };
          const queryString = new URLSearchParams(data).toString();
          const url = `${admin_url}whatsapp/get_aireply?${queryString}`;
          const response = await fetch(url, {
            method: 'GET'
          });
          if (!response.ok) {
            throw new Error('Failed to generate AI response');
          }
          const responseData = await response.json();
          if (responseData.reply) {
            this.whatsapp_newMessage = responseData.reply;
          } else {
            console.error('No reply received from AI');
          }
        } catch (error) {
          console.error('Error generating AI response:', error);
        }
      },
      openModal() {
        this.isModalOpen = true;
      },
      closeModal() {
        this.isModalOpen = false;
      },
      submitForm() {
        // Ensure a sender number is selected before proceeding.
        if (!this.selectedFromNumber) {
          alert('Please select a sender number.');
          return;
        }

        // Create a new FormData object and append the required fields.
        const payload = new FormData();
        payload.append('csrf_token_name', this.whatsapp_csrfToken);
        payload.append('from_number_id', this.selectedFromNumber); // Sender's phone number ID
        payload.append('phone_number', this.phoneNumber);
        payload.append('template_id', this.templateId);

        const url = `${admin_url}whatsapp/initiate_message`;

        // Use fetch API to send the POST request.
        fetch(url, {
            method: 'POST',
            body: payload
          })
          .then(response => {
            // Check if the response status is OK (200-299)
            if (!response.ok) {
              throw new Error(`Server responded with ${response.status}`);
            }
            return response.json();
          })
          .then(data => {
            console.log('Initiate message response:', data);
            if (data.success) {
              this.closeModal(); // Close the modal on success.
            } else {
              alert(data.message || 'Failed to send the message.');
            }
          })
          .catch(error => {
            console.error('Error sending message:', error);
            alert('An error occurred while sending the message. Please try again later.');
          });
      },
    },
    computed: {
      whatsapp_showSendButton() {
        return !!(
          this.whatsapp_imageAttachment ||
          this.whatsapp_videoAttachment ||
          this.whatsapp_documentAttachment ||
          this.whatsapp_newMessage.trim() ||
          this.selectedTemplateId ||
          this.whatsapp_audioBlob
        );
      },
      filteredEmojis() {
        return this.emojis ? this.emojis[this.selectedCategory] : [];
      },
      templatesWithoutParameters() {
        return this.whatsapp_templates.filter(template =>
          template.header_params_count === "0" &&
          template.body_params_count === "0" &&
          template.footer_params_count === "0"
        );
      },
      totalUnread() {
        return this.interactions.reduce((sum, interaction) => {
          return sum + (parseInt(interaction.unread) || 0);
        }, 0);
      },
      // Group filter-related properties into one computed object
      filters() {
        return {
          waNo: this.whatsapp_selectedWaNo,
          interactionType: this.whatsapp_selectedInteractionType,
          status: this.whatsapp_selectedStatus,
          assigned: this.whatsapp_selectedAssigned,
          unread: this.whatsapp_selectedUnread,
          searchText: this.whatsapp_searchText
        };
      }
    },
    watch: {
      filters: {
        handler(newFilters) {
          clearTimeout(this.filterTimeout);
          this.filterTimeout = setTimeout(() => {
            this.updateProfileData();
            // Simply re-fetch the interactions from the server.
            this.whatsapp_fetchInteractions(false);
          }, 300);
        },
        deep: true,
      },
      whatsapp_selectedWaNo(newVal, oldVal) {
        this.updateProfileData();
        this.whatsapp_fetchInteractions(false);
      }
    },
    async created() {
      await this.whatsapp_fetchInteractions(false);
      this.updateProfileData();
      this.loadEmojis();
      if (!this.isIntervalPaused) {
        this.interactionRefreshInterval = setInterval(() => {
          this.whatsapp_fetchInteractions(true);
          if (this.whatsapp_selectedinteraction) {
            this.whatsapp_selectinteraction(this.whatsapp_selectedinteraction.id);
          }
        }, this.reloadingtime * 1000);
      }
    },
    beforeDestroy() {
      if (this.interactionRefreshInterval) {
        clearInterval(this.interactionRefreshInterval);
      }
      if (this.messageRefreshInterval) {
        clearInterval(this.messageRefreshInterval);
      }
    }
  });
</script>
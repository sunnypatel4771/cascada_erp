<?php
// Example: fetch all approved templates from DB (if you use them)
$allTemplates = get_whatsapp_template(); 
// Convert to JSON for embedding in the page:
$templatesJson = json_encode($allTemplates);

// If you have an existing flow record:
$flowData = $flow['flow_data'] ?? '{}';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Advanced WhatsApp Flow Builder</title>
  <!-- Tailwind CSS -->
  <link rel="stylesheet" href="<?= htmlspecialchars(module_dir_url('whatsapp', 'assets/css/twailwind.css')) ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(module_dir_url('whatsapp', 'assets/css/botflow.css')) ?>">
  <style>
    /* Prevent text selection during drag operations */
    body, .node, #builder {
      -webkit-user-select: none;
      -moz-user-select: none;
      -ms-user-select: none;
      user-select: none;
    }
    /* Animation for SVG path drawing */
    @keyframes dash {
      to { stroke-dashoffset: 0; }
    }
    /* Delete icon styling */
    .node .delete-node {
      position: absolute;
      top: -6px;
      right: -6px;
      background: red;
      color: #fff;
      width: 20px;
      height: 20px;
      border-radius: 50%;
      text-align: center;
      line-height: 20px;
      font-weight: bold;
      cursor: pointer;
    }
    /* Output and branch output points */
    .output-point, .branch-output {
      width: 12px;
      height: 12px;
      background: green;
      border-radius: 50%;
      cursor: pointer;
      position: absolute;
    }
    .output-point {
      top: 50%;
      right: 0;
      transform: translateY(-50%);
    }
    /* Builder grid background */
    #builder {
      background-image: linear-gradient(to right, #ccc 1px, transparent 1px),
                        linear-gradient(to bottom, #ccc 1px, transparent 1px);
      background-size: 20px 20px;
    }
    /* Dark theme styles */
    .dark-theme body {
      background-color: #1a202c;
      color: #cbd5e0;
    }
    .dark-theme .node {
      background-color: #2d3748;
      border-color: #4a5568;
      color: #e2e8f0;
    }
    /* Annotation icon style */
    .annotation-icon {
      position: absolute;
      bottom: -5px;
      right: -5px;
      background: yellow;
      border-radius: 50%;
      width: 16px;
      height: 16px;
      text-align: center;
      line-height: 16px;
      font-size: 10px;
      cursor: pointer;
    }
    /* Resize handle for nodes (optional feature) */
    .resize-handle {
      position: absolute;
      bottom: 0;
      right: 0;
      width: 12px;
      height: 12px;
      background-color: #4c8bf5;
      cursor: nwse-resize;
    }
  </style>
</head>
<body class="bg-gray-100">

  <!-- Sidebar -->
  <div class="w-64 bg-white shadow-lg p-4 h-screen fixed left-0 overflow-y-auto">
    <h3 class="text-lg font-semibold mb-4">WhatsApp Message Types</h3>
    <!-- Minimal Node Types -->
    <div class="component bg-green-700 text-white p-2 mb-2 rounded cursor-pointer" data-type="start" draggable="true">
      🚀 Start Node
    </div>
    <!-- Template Node (special) -->
    <div class="component bg-orange-500 text-white p-2 mb-2 rounded cursor-pointer" data-type="template" draggable="true">
      📝 Template
    </div>

    <div class="component bg-blue-500 text-white p-2 mb-2 rounded cursor-pointer" data-type="text" draggable="true">
      📩 Text Message
    </div>
    <div class="component bg-blue-500 text-white p-2 mb-2 rounded cursor-pointer" data-type="image" draggable="true">
      🖼 Image
    </div>
    <div class="component bg-blue-500 text-white p-2 mb-2 rounded cursor-pointer" data-type="audio" draggable="true">
      🎵 Audio
    </div>
    <div class="component bg-blue-500 text-white p-2 mb-2 rounded cursor-pointer" data-type="video" draggable="true">
      🎥 Video
    </div>
    <div class="component bg-red-700 text-white p-2 mb-2 rounded cursor-pointer" data-type="end" draggable="true">
      🏁 End Node
    </div>
    <div class="component bg-green-500 text-white p-2 mb-2 rounded cursor-pointer" data-type="delay" draggable="true">
      ⏱ Delay
    </div>

  </div>

  <!-- Flow Builder Area -->
  <div id="builder" class="ml-64 h-screen bg-gray-200 relative overflow-hidden">
    <!-- SVG container for connections -->
    <svg id="arrow-container" class="absolute top-0 left-0 w-full h-full pointer-events-none"></svg>
  </div>

  <!-- Node Edit Modal -->
  <div id="modal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex justify-center items-center z-50">
    <div id="modal-content" class="bg-white p-6 rounded shadow-lg w-96 transform scale-95 opacity-0 transition-all duration-300">
      <h3 class="text-lg font-semibold mb-4">Edit Node</h3>
      <div id="modal-fields"></div>
      <div class="flex justify-between mt-4">
        <button id="modal-delete" class="bg-red-500 text-white px-4 py-2 rounded">Delete Node</button>
        <div class="space-x-2">
          <button id="modal-cancel" class="bg-gray-500 text-white px-4 py-2 rounded">Cancel</button>
          <button id="modal-save" class="bg-blue-500 text-white px-4 py-2 rounded">Save</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Export / Import Modal (also used to display flow data) -->
  <div id="export-modal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex justify-center items-center z-50">
    <div id="export-content" class="bg-white p-6 rounded shadow-lg w-1/2 transform scale-95 opacity-0 transition-all duration-300">
      <h3 class="text-lg font-semibold mb-4" id="export-header">Export / Import Flow</h3>
      <textarea id="export-text" class="w-full h-64 p-2 border rounded" placeholder="Flow JSON will appear here..." readonly></textarea>
      <div class="flex justify-end space-x-2 mt-4">
        <button id="export-close" class="bg-gray-500 text-white px-4 py-2 rounded">Close</button>
        <button id="export-save" class="bg-green-500 text-white px-4 py-2 rounded">Export Flow</button>
        <button id="import-flow" class="bg-blue-500 text-white px-4 py-2 rounded">Import Flow</button>
      </div>
    </div>
  </div>

  <!-- Bottom Action Buttons -->
  <div class="fixed bottom-5 right-5 space-x-2 z-40">
    <button id="undo" class="bg-gray-500 text-white px-4 py-2 rounded">Undo</button>
    <button id="redo" class="bg-gray-500 text-white px-4 py-2 rounded">Redo</button>
    <button id="group-selected" class="bg-purple-600 text-white px-4 py-2 rounded">Group Selected</button>
    <button id="load-flow" class="bg-blue-500 text-white px-4 py-2 rounded">Load Flow</button>
    <button id="export-png" class="bg-green-600 text-white px-4 py-2 rounded">Export PNG</button>
    <button id="preview" class="bg-blue-600 text-white px-4 py-2 rounded">Preview</button>
    <button id="save-flow" class="bg-green-500 text-white px-4 py-2 rounded">Save Flow</button>
    <!-- New Button: Show Flow Data -->
    <button id="show-flow" class="bg-yellow-500 text-white px-4 py-2 rounded">Show Flow Data</button>
  </div>

  <!-- PHP Form for Saving the Flow Data -->
  <?php echo form_open(admin_url('whatsapp/bots/saveBot'), ['id' => 'workflow-form', 'autocomplete' => 'off']); ?>
    <input type="hidden" name="id" id="id" value="<?php echo $flow['id'] ?? ''; ?>" />
    <input type="hidden" name="flow_data" id="flow_data" value='<?= $flowData ?>' />
    <div id="whatsapp-vue-app"></div>
  <?php echo form_close(); ?>

  <!-- Include html2canvas for PNG export -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

  <!-- Embed your templates from PHP into JS -->
  <script>
    const ALL_TEMPLATES = <?php echo $templatesJson; ?>;
    /*
      ALL_TEMPLATES might look like:
      [
        {
          "id": "12",
          "template_name": "my_template",
          "language": "en_US",
          "status": "APPROVED",
          ...
        },
        ...
      ]
    */
  </script>

<script>
(function () {
  "use strict";

  // Cached DOM Elements
  const builder       = document.getElementById("builder"),
        svgContainer  = document.getElementById("arrow-container"),
        modal         = document.getElementById("modal"),
        modalContent  = document.getElementById("modal-content"),
        modalFields   = document.getElementById("modal-fields"),
        exportModal   = document.getElementById("export-modal"),
        exportContent = document.getElementById("export-content"),
        exportText    = document.getElementById("export-text"),
        exportHeader  = document.getElementById("export-header"),
        form          = document.getElementById("workflow-form"),
        flowDataInput = document.getElementById("flow_data"),
        showFlowBtn   = document.getElementById("show-flow");

  let draggedComponent = null,
      selectedNode     = null,
      connectionStart  = null,
      connections      = [],
      nodesData        = {},
      undoStack        = [],
      redoStack        = [],
      previewMode      = false,
      selectedNodes    = new Set();

  /*** DRAG & DROP SETUP ***/
  document.querySelectorAll(".component").forEach(component => {
    component.addEventListener("dragstart", (event) => {
      draggedComponent = event.target.getAttribute("data-type");
      event.dataTransfer.setData("text/plain", draggedComponent);
    });
  });
  builder.addEventListener("dragover", (event) => {
    event.preventDefault();
  });
  builder.addEventListener("drop", (event) => {
    event.preventDefault();
    if (draggedComponent) {
      const rect = builder.getBoundingClientRect();
      const position = { x: event.clientX - rect.left, y: event.clientY - rect.top };
      addNode(draggedComponent, position);
      pushUndoState();
      draggedComponent = null;
    }
  });

  /*** UNDO / REDO ***/
  function getCurrentFlowState() {
    const flow = { nodes: [], connections: [] };
    builder.querySelectorAll(".node").forEach(node => {
      const id = node.getAttribute("data-id");
      flow.nodes.push({
        id: id,
        type: node.getAttribute("data-type"),
        position: { x: parseFloat(node.style.left), y: parseFloat(node.style.top) },
        data: nodesData[id].data
      });
    });
    connections.forEach(conn => {
      flow.connections.push({
        from: conn.from,
        to: conn.to,
        branch: conn.branch,
        label: conn.label || ""
      });
    });
    return flow;
  }
  function pushUndoState() {
    undoStack.push(JSON.stringify(getCurrentFlowState()));
    redoStack = [];
  }
  function undo() {
    if (undoStack.length) {
      redoStack.push(JSON.stringify(getCurrentFlowState()));
      const state = undoStack.pop();
      loadFlow(JSON.parse(state));
    }
  }
  function redo() {
    if (redoStack.length) {
      undoStack.push(JSON.stringify(getCurrentFlowState()));
      const state = redoStack.pop();
      loadFlow(JSON.parse(state));
    }
  }

  /*** NODE DATA HELPERS ***/
  function getDefaultNodeData(type) {
    switch(type) {
      case "text":
        return { text: "Double-click to edit text message...", script: "", annotation: "" };
      case "image":
        return { url: "#", caption: "", script: "", annotation: "" };
      case "audio":
        return { url: "https://samplelib.com/lib/preview/mp3/sample-9s.mp3", script: "", annotation: "" };
      case "video":
        return { url: "https://sample-videos.com/video321/mp4/720/big_buck_bunny_720p_1mb.mp4", caption: "", script: "", annotation: "" };
      case "document":
        return { url: "https://example.com/document.pdf", filename: "document.pdf", script: "", annotation: "" };
      case "location":
        return { latitude: "0.0", longitude: "0.0", name: "Location Name", address: "", script: "", annotation: "" };
      case "interactive_button":
        return { body: "Interactive Button Message", buttons: [ { id: "btn1", title: "Button 1" } ], script: "", annotation: "" };
      case "interactive_list":
        return {
          header: "List Header",
          body: "Interactive List Message",
          footer: "",
          actionButton: "Choose",
          sections: [
            { title: "Section 1", rows: [ { id: "row1", title: "Row 1", description: "Row 1 description" } ] }
          ],
          script: "",
          annotation: ""
        };
      case "template":
        return { template_id: null, template_name: "", language: "en_US", script: "", annotation: "" };
      case "api":
        return { endpoint: "https://api.example.com", method: "GET", payload: "{}", script: "", annotation: "" };
      case "cta":
        return {
          buttons: [
            { id: "cta1", title: "CTA 1", url: "https://example.com/1" },
            { id: "cta2", title: "CTA 2", url: "https://example.com/2" },
            { id: "cta3", title: "CTA 3", url: "https://example.com/3" }
          ],
          script: "",
          annotation: ""
        };
      case "condition":
        return {
          conditions: [ { field: "user_input", operator: ">", value: "5", logic: "AND" } ],
          outcomes: [ { label: "True" }, { label: "False" } ],
          script: "",
          annotation: ""
        };
      case "delay":
        return { delay: "1000", script: "", annotation: "" };
      case "start":
        return { message: "Start your flow", triggerCommand: "init", triggerType: "start", script: "", annotation: "" };
      case "end":
        return { message: "End your flow", script: "", annotation: "" };
      default:
        return { content: "Double-click to edit...", script: "", annotation: "" };
    }
  }

  function getNodeInnerContent(type, data) {
    let innerContent = "";
    if (type === "image") {
      innerContent = `
        <div class="node-preview">
          <img src="${data.url.trim()}" alt="Image Preview" class="max-w-full h-32 object-cover rounded" />
        </div>
        <div class="node-summary mt-2 text-sm text-gray-600">${(data.caption || "No caption").trim()}</div>`;
    } else if (type === "video") {
      innerContent = `
        <div class="node-preview">
          <video src="${data.url.trim()}" controls class="max-w-full h-32 rounded"></video>
        </div>
        <div class="node-summary mt-2 text-sm text-gray-600">${(data.caption || "No caption").trim()}</div>`;
    } else if (type === "document") {
      innerContent = `
        <div class="node-preview">
          <iframe src="${data.url.trim()}" class="w-full h-32 rounded" frameborder="0"></iframe>
        </div>
        <div class="node-summary mt-2 text-sm text-gray-600">${(data.filename || "").trim()}</div>`;
    } else if (type === "audio") {
      innerContent = `
        <audio controls class="max-w-full">
          <source src="${data.url.trim()}" type="audio/mpeg" />
          Your browser does not support the audio element.
        </audio>`;
    } else if (type === "start") {
      innerContent = `<div class="node-summary mt-2 text-sm text-gray-600">Start: ${(data.message || "").trim()}</div>`;
    } else if (type === "end") {
      innerContent = `<div class="node-summary mt-2 text-sm text-gray-600">End: ${(data.message || "").trim()}</div>`;
    } else if (type === "text") {
      innerContent = `<div class="node-summary mt-2 text-sm text-gray-600">${(data.text || "").trim()}</div>`;
    } else if (type === "location") {
      innerContent = `<div class="node-summary mt-2 text-sm text-gray-600">
        Lat: ${(data.latitude || "").trim()}, Long: ${(data.longitude || "").trim()} (${(data.name || "").trim()})
      </div>`;
    } else if (type === "interactive_button") {
      innerContent = `<div class="node-summary mt-2 text-sm text-gray-600">${(data.body || "").trim()}</div>`;
    } else if (type === "interactive_list") {
      innerContent = `<div class="node-summary mt-2 text-sm text-gray-600">${(data.body || "").trim()}</div>`;
    } else if (type === "template") {
      const tname = (data.template_name || "(no template selected)").trim();
      innerContent = `<div class="node-summary mt-2 text-sm text-gray-600">Template: ${tname}</div>`;
    } else if (type === "condition") {
      const condSummary = data.conditions
        ? data.conditions.map(c => `[${c.field.trim()} ${c.operator.trim()} ${c.value.trim()}]`).join(` ${data.conditions[0]?.logic.trim() || 'AND'} `)
        : "No Conditions";
      innerContent = `<div class="node-summary mt-2 text-sm text-gray-600">Conditions: ${condSummary}</div>`;
    } else if (type === "delay") {
      innerContent = `<div class="node-summary mt-2 text-sm text-gray-600">${(data.delay || "").trim()}</div>`;
    } else {
      innerContent = `<div class="node-summary mt-2 text-sm text-gray-600">${(data.content || "").trim()}</div>`;
    }
    return innerContent;
  }

  function getNodeSummary(type, data) {
    switch(type) {
      case "text": return (data.text || "").trim();
      case "audio": return (data.url || "").trim();
      case "location": return `Lat: ${(data.latitude || "").trim()}, Long: ${(data.longitude || "").trim()} (${(data.name || "").trim()})`;
      case "interactive_button": return (data.body || "").trim();
      case "interactive_list": return (data.body || "").trim();
      case "template": return (data.template_name || "(No Template)").trim();
      case "delay": return (data.delay || "").trim();
      case "start": return "Start Node: " + (data.message || "").trim();
      case "end": return "End Node: " + (data.message || "").trim();
      default: return "";
    }
  }

  function renderOutputPoints(type, data) {
    if (type === "end") return "";
    if (["interactive_button", "interactive_list", "condition"].includes(type)) {
      if (type === "interactive_button") {
        return data.buttons.map((btn, idx) =>
          `<div class="branch-output" data-branch-index="${idx}" title="Branch for button: ${btn.title.trim()}" 
            style="position:absolute; right:0; top:${20 + idx * 24}px;"></div>`
        ).join('');
      } else if (type === "interactive_list") {
        return data.sections[0].rows.map((row, idx) =>
          `<div class="branch-output" data-branch-index="${idx}" title="Branch for row: ${row.title.trim()}" 
            style="position:absolute; right:0; top:${20 + idx * 24}px;"></div>`
        ).join('');
      } else if (type === "condition") {
        return data.outcomes.map((outcome, idx) => `
          <div class="branch-output" data-branch-index="${idx}" title="Outcome: ${outcome.label.trim()}"
            style="position:absolute; right:0; top:${20 + idx * 24}px; width:12px; height:12px; background:green; border-radius:50%; cursor:pointer;"></div>
        `).join('');
      }
    }
    return `<div class="output-point" data-point="output"></div>`;
  }

  /*** NODE MANAGEMENT ***/
  function addNode(type, position, customData = {}) {
    const nodeId = customData.id ? customData.id : "node-" + Date.now() + "-" + Math.floor(Math.random() * 1000);
    const node = document.createElement("div");
    node.className = "node bg-white shadow-lg p-4 border cursor-pointer absolute";
    node.style.left = position.x + "px";
    node.style.top = position.y + "px";
    node.setAttribute("data-id", nodeId);
    node.setAttribute("data-type", type);

    let defaultData = { ...getDefaultNodeData(type), ...customData };
    nodesData[nodeId] = { id: nodeId, type: type, position: position, data: defaultData };

    const innerContent = getNodeInnerContent(type, defaultData);
    let inputPointHTML = "";
    let outputPointHTML = "";
    if (type !== "start") {
      inputPointHTML = `<div class="input-point" data-point="input" style="position:absolute; top:50%; left:0; transform:translateY(-50%); width:12px; height:12px; background:blue; border-radius:50%; cursor:pointer;"></div>`;
    }
    if (type !== "end") {
      outputPointHTML = renderOutputPoints(type, defaultData);
    }
    node.innerHTML = `
      <div class="delete-node">×</div>
      <strong class="text-blue-600 uppercase">${type}</strong>
      <div class="node-content">${innerContent}</div>
      ${inputPointHTML}
      ${outputPointHTML}
    `;

    if (defaultData.annotation) {
      const annotationEl = document.createElement("div");
      annotationEl.className = "annotation-icon";
      annotationEl.textContent = "!";
      annotationEl.title = defaultData.annotation;
      node.appendChild(annotationEl);
    }

    node.querySelectorAll(".branch-output").forEach(output => {
      output.addEventListener("mousedown", (e) => {
        e.stopPropagation();
        connectionStart = {
          node: node,
          point: output,
          branchIndex: output.getAttribute("data-branch-index")
        };
        document.addEventListener("mousemove", drawTemporaryConnection);
      });
    });
    node.querySelectorAll(".output-point").forEach(output => {
      output.addEventListener("mousedown", (e) => {
        e.stopPropagation();
        connectionStart = { node: node, point: output };
        document.addEventListener("mousemove", drawTemporaryConnection);
      });
    });

    node.addEventListener("dblclick", (e) => {
      if (!e.target.classList.contains("delete-node")) {
        openEditModal(node);
      }
    });
    node.querySelector(".delete-node").addEventListener("click", (e) => {
      e.stopPropagation();
      deleteNode(node);
      pushUndoState();
    });
    builder.appendChild(node);
    makeNodeDraggable(node);
  }

  function makeNodeDraggable(node) {
    node.addEventListener("mousedown", (event) => {
      if (event.target.hasAttribute("data-point") || event.target.classList.contains("branch-output"))
        return;
      const startX = event.clientX, startY = event.clientY;
      const nodeRect = node.getBoundingClientRect(), builderRect = builder.getBoundingClientRect();
      function onMouseMove(e) {
        let newLeft = nodeRect.left + (e.clientX - startX) - builderRect.left;
        let newTop = nodeRect.top + (e.clientY - startY) - builderRect.top;
        const gridSize = 20;
        newLeft = Math.round(newLeft / gridSize) * gridSize;
        newTop = Math.round(newTop / gridSize) * gridSize;
        node.style.left = newLeft + "px";
        node.style.top = newTop + "px";
        const nodeId = node.getAttribute("data-id");
        if (nodesData[nodeId]) {
          nodesData[nodeId].position = { x: newLeft, y: newTop };
        }
        updateConnections();
      }
      function onMouseUp() {
        document.removeEventListener("mousemove", onMouseMove);
        document.removeEventListener("mouseup", onMouseUp);
        pushUndoState();
      }
      document.addEventListener("mousemove", onMouseMove);
      document.addEventListener("mouseup", onMouseUp);
    });
  }

  function deleteNode(node) {
    const nodeId = node.getAttribute("data-id");
    connections = connections.filter(conn => {
      if (conn.from === nodeId || conn.to === nodeId) {
        conn.pathEl.remove();
        return false;
      }
      return true;
    });
    delete nodesData[nodeId];
    node.remove();
    updateConnections();
  }

  /*** CONNECTION CREATION ***/
  builder.addEventListener("mousedown", (event) => {
    const outputPoint = event.target.closest("[data-point='output']");
    if (outputPoint) {
      connectionStart = { node: outputPoint.closest(".node"), point: outputPoint };
      document.addEventListener("mousemove", drawTemporaryConnection);
    }
  });
  document.addEventListener("mouseup", (event) => {
    if (connectionStart) {
      const inputPoint = event.target.closest("[data-point='input']");
      if (inputPoint) {
        const startNode = connectionStart.node;
        const endNode = inputPoint.closest(".node");
        if (startNode !== endNode) {
          createConnection(startNode, endNode, connectionStart.branchIndex);
          pushUndoState();
        }
      }
      connectionStart = null;
      removeTemporaryConnection();
    }
    document.removeEventListener("mousemove", drawTemporaryConnection);
  });

  function drawTemporaryConnection(event) {
    const rect = builder.getBoundingClientRect();
    const startRect = connectionStart.point.getBoundingClientRect();
    const startX = startRect.right - rect.x;
    const startY = startRect.top - rect.y + 8;
    const currentX = event.clientX - rect.x;
    const currentY = event.clientY - rect.y;
    svgContainer.innerHTML = `<path d="M${startX},${startY} L${currentX},${currentY}" stroke="black" fill="none" stroke-width="2" id="temp-path" />`;
  }
  function removeTemporaryConnection() {
    const tempPath = document.getElementById("temp-path");
    if (tempPath) {
      tempPath.remove();
    }
  }
  function createConnection(startNode, endNode, branchIndex) {
    const builderRect = builder.getBoundingClientRect();
    let startPointElement;
    if (branchIndex !== undefined && branchIndex !== null) {
      startPointElement = startNode.querySelector(`.branch-output[data-branch-index="${branchIndex}"]`);
    } else {
      startPointElement = startNode.querySelector("[data-point='output']");
    }
    if (!startPointElement) return;
    const startPoint = startPointElement.getBoundingClientRect();
    const endPoint = endNode.querySelector("[data-point='input']").getBoundingClientRect();
    const path = document.createElementNS("http://www.w3.org/2000/svg", "path");
    const startX = startPoint.right - builderRect.x;
    const startY = startPoint.top - builderRect.y + 8;
    const endX = endPoint.left - builderRect.x;
    const endY = endPoint.top - builderRect.y + 8;
    const controlX1 = startX + Math.abs(endX - startX) / 2;
    const controlY1 = startY;
    const controlX2 = startX + Math.abs(endX - startX) / 2;
    const controlY2 = endY;
    path.setAttribute("d", `M${startX},${startY} C${controlX1},${controlY1} ${controlX2},${controlY2} ${endX},${endY}`);
    path.setAttribute("stroke", "black");
    path.setAttribute("stroke-dasharray", "5,5");
    path.setAttribute("fill", "none");
    path.setAttribute("stroke-width", "2");
    path.style.cursor = "pointer";
    path.style.animation = "dash 0.1s linear forwards";
    path.addEventListener("dblclick", () => {
      const label = prompt("Enter label for connection:", "");
      if (label !== null) {
        const conn = connections.find(c => c.pathEl === path);
        if (conn) {
          conn.label = label;
          updateConnections();
          pushUndoState();
        }
      }
    });
    path.addEventListener("click", () => {
      removeConnectionByPath(path);
      pushUndoState();
    });
    svgContainer.appendChild(path);
    const fromId = startNode.getAttribute("data-id");
    const toId = endNode.getAttribute("data-id");
    const conn = { from: fromId, to: toId, branch: branchIndex, pathEl: path, label: "" };
    connections.push(conn);
  }
  function removeConnectionByPath(pathEl) {
    connections = connections.filter(conn => {
      if (conn.pathEl === pathEl) {
        pathEl.remove();
        return false;
      }
      return true;
    });
  }
  function updateConnections() {
    svgContainer.innerHTML = "";
    const groups = {};
    connections.forEach(conn => {
      const key = conn.from + "_" + (conn.branch !== undefined && conn.branch !== null ? conn.branch : "default");
      if (!groups[key]) groups[key] = [];
      groups[key].push(conn);
    });
    Object.values(groups).forEach(group => {
      group.forEach((conn, idx) => {
        const startNode = builder.querySelector(`[data-id="${conn.from}"]`);
        const endNode = builder.querySelector(`[data-id="${conn.to}"]`);
        if (startNode && endNode) {
          const builderRect = builder.getBoundingClientRect();
          let startPointElement;
          if (conn.branch !== undefined && conn.branch !== null) {
            startPointElement = startNode.querySelector(`.branch-output[data-branch-index="${conn.branch}"]`);
          } else {
            startPointElement = startNode.querySelector("[data-point='output']");
          }
          if (!startPointElement) return;
          const startPoint = startPointElement.getBoundingClientRect();
          const offset = idx * 5;
          const startX = startPoint.right - builderRect.x;
          const startY = startPoint.top - builderRect.y + 8 + offset;
          const endPoint = endNode.querySelector("[data-point='input']").getBoundingClientRect();
          const endX = endPoint.left - builderRect.x;
          const endY = endPoint.top - builderRect.y + 8;
          const newPath = document.createElementNS("http://www.w3.org/2000/svg", "path");
          const controlX1 = startX + Math.abs(endX - startX) / 2;
          const controlY1 = startY;
          const controlX2 = startX + Math.abs(endX - startX) / 2;
          const controlY2 = endY;
          newPath.setAttribute("d", `M${startX},${startY} C${controlX1},${controlY1} ${controlX2},${controlY2} ${endX},${endY}`);
          newPath.setAttribute("stroke", "black");
          newPath.setAttribute("fill", "none");
          newPath.setAttribute("stroke-width", "2");
          newPath.setAttribute("stroke-dasharray", "5,5");
          newPath.style.animation = "dash 0.1s linear forwards";
          newPath.style.cursor = "pointer";
          newPath.addEventListener("click", () => {
            removeConnectionByPath(newPath);
            pushUndoState();
          });
          svgContainer.appendChild(newPath);
          if (conn.label) {
            const textEl = document.createElementNS("http://www.w3.org/2000/svg", "text");
            const midX = (startX + endX) / 2;
            const midY = (startY + endY) / 2;
            textEl.setAttribute("x", midX);
            textEl.setAttribute("y", midY);
            textEl.textContent = conn.label;
            textEl.setAttribute("fill", "red");
            svgContainer.appendChild(textEl);
          }
          conn.pathEl = newPath;
        }
      });
    });
  }

  /* NODE EDIT MODAL */
  function openEditModal(node) {
    selectedNode = node;
    const type = node.getAttribute("data-type");
    modalFields.innerHTML = "";
    const nodeId = node.getAttribute("data-id");
    const nodeData = nodesData[nodeId].data;
    let html = "";

    if (type === "condition") {
      html += `<div class="mb-4">
                 <label class="block font-semibold">Conditions:</label>
                 <div id="condition-rows" class="mt-2 space-y-2">`;
      if (!nodeData.conditions || !nodeData.conditions.length) {
        nodeData.conditions = [{ field: "user_input", operator: ">", value: "5", logic: "AND" }];
      }
      nodeData.conditions.forEach((cond, idx) => {
        html += `
          <div class="border p-2 rounded" data-cond-index="${idx}">
            <div class="flex space-x-2 mb-1">
              <input type="text" class="condition-field w-1/3 p-1 border rounded" placeholder="Field" value="${(cond.field || "").trim()}" />
              <select class="condition-operator w-1/3 p-1 border rounded">
                <option value="=" ${(cond.operator === "=") ? "selected" : ""}>=</option>
                <option value="!=" ${(cond.operator === "!=") ? "selected" : ""}>&neq;</option>
                <option value=">" ${(cond.operator === ">") ? "selected" : ""}>&gt;</option>
                <option value=">=" ${(cond.operator === ">=") ? "selected" : ""}>&ge;</option>
                <option value="<" ${(cond.operator === "<") ? "selected" : ""}>&lt;</option>
                <option value="<=" ${(cond.operator === "<=") ? "selected" : ""}>&le;</option>
                <option value="CONTAINS" ${(cond.operator === "CONTAINS") ? "selected" : ""}>CONTAINS</option>
                <option value="NOT CONTAINS" ${(cond.operator === "NOT CONTAINS") ? "selected" : ""}>NOT CONTAINS</option>
              </select>
              <input type="text" class="condition-value w-1/3 p-1 border rounded" placeholder="Value" value="${(cond.value || "").trim()}" />
            </div>
            <div class="flex items-center justify-between">
              <label class="block">Logic to next:</label>
              <select class="condition-logic p-1 border rounded">
                <option value="AND" ${(cond.logic === "AND") ? "selected" : ""}>AND</option>
                <option value="OR" ${(cond.logic === "OR") ? "selected" : ""}>OR</option>
              </select>
              <button type="button" class="remove-condition bg-red-500 text-white px-2 py-1 rounded">Remove</button>
            </div>
          </div>`;
      });
      html += `</div>
               <button type="button" id="add-condition-row" class="mt-2 bg-green-500 text-white px-2 py-1 rounded">+ Add Condition</button>
               </div>
               <div class="mb-4">
                 <label class="block font-semibold">Outcomes (Fixed: True / False):</label>
                 <p class="text-sm text-gray-600">This node always has 2 outcomes: True (index=0) and False (index=1).</p>
               </div>`;
      modalFields.innerHTML = html;
      modal.style.display = "flex";
      setTimeout(() => { modalContent.classList.add("scale-100", "opacity-100"); }, 10);
      const addConditionBtn = document.getElementById("add-condition-row");
      if (addConditionBtn) {
        addConditionBtn.addEventListener("click", () => {
          const condContainer = document.getElementById("condition-rows");
          const newIdx = condContainer.children.length;
          const newRow = document.createElement("div");
          newRow.className = "border p-2 rounded";
          newRow.setAttribute("data-cond-index", newIdx);
          newRow.innerHTML = `
            <div class="flex space-x-2 mb-1">
              <input type="text" class="condition-field w-1/3 p-1 border rounded" placeholder="Field" value="user_input" />
              <select class="condition-operator w-1/3 p-1 border rounded">
                <option value="=">=</option>
                <option value="!=">&neq;</option>
                <option value=">">&gt;</option>
                <option value=">=">&ge;</option>
                <option value="<">&lt;</option>
                <option value="<=">&le;</option>
                <option value="CONTAINS">CONTAINS</option>
                <option value="NOT CONTAINS">NOT CONTAINS</option>
              </select>
              <input type="text" class="condition-value w-1/3 p-1 border rounded" placeholder="Value" value="" />
            </div>
            <div class="flex items-center justify-between">
              <label class="block">Logic to next:</label>
              <select class="condition-logic p-1 border rounded">
                <option value="AND">AND</option>
                <option value="OR">OR</option>
              </select>
              <button type="button" class="remove-condition bg-red-500 text-white px-2 py-1 rounded">Remove</button>
            </div>`;
          condContainer.appendChild(newRow);
          const rmCondBtn = newRow.querySelector(".remove-condition");
          if (rmCondBtn) {
            rmCondBtn.addEventListener("click", () => {
              newRow.remove();
            });
          }
        });
      }
      document.querySelectorAll(".remove-condition").forEach(btn => {
        btn.addEventListener("click", (ev) => {
          ev.target.closest(".border").remove();
        });
      });
    }
    else if (type === "text") {
      html += `<label class="block mb-2">Text Body:</label>
               <textarea id="field_text" class="modal-input w-full p-2 border rounded" rows="2">${(nodeData.text || "").trim()}</textarea>
               <p class="text-sm text-gray-600 mt-2">Provide the main text for this node.</p>`;
      modalFields.innerHTML = html;
      modal.style.display = "flex";
      setTimeout(() => { modalContent.classList.add("scale-100", "opacity-100"); }, 10);
    }
    else if (type === "template") {
      html += `<label class="block mb-2">Select Template:</label>
               <select id="field_template_id" class="modal-input w-full p-2 border rounded">
                 <option value="">-- Select a template --</option>`;
      ALL_TEMPLATES.forEach(tpl => {
        const selected = (tpl.id == nodeData.template_id) ? "selected" : "";
        html += `<option value="${tpl.id}" ${selected}>${tpl.template_name} [${tpl.language}]</option>`;
      });
      html += `</select>
               <p class="text-sm text-gray-600 mt-2">Choose a pre-approved template from the list.</p>
               <label class="block mt-4 mb-2">Script (optional):</label>
               <textarea id="field_script" class="modal-input w-full p-2 border rounded" rows="2">${(nodeData.script || "").trim()}</textarea>`;
      modalFields.innerHTML = html;
      modal.style.display = "flex";
      setTimeout(() => { modalContent.classList.add("scale-100", "opacity-100"); }, 10);
    }
    else if (type === "interactive_button") {
      html += `<label class="block mb-2">Body Text:</label>
               <textarea id="field_body" class="modal-input w-full p-2 border rounded" rows="2">${(nodeData.body || "").trim()}</textarea>
               <p class="text-sm text-gray-600 mt-2">Interactive Button message text</p>
               <label class="block mt-4 mb-2">Buttons (JSON or Array):</label>
               <textarea id="field_buttons" class="modal-input w-full p-2 border rounded" rows="3">${JSON.stringify(nodeData.buttons || [], null, 2).trim()}</textarea>`;
      modalFields.innerHTML = html;
      modal.style.display = "flex";
      setTimeout(() => { modalContent.classList.add("scale-100", "opacity-100"); }, 10);
    }
    else if (type === "interactive_list") {
      html += `<label class="block mb-2">Header:</label>
               <input type="text" id="field_header" class="modal-input w-full p-2 border rounded" value="${(nodeData.header || "").trim()}" />
               <label class="block mb-2 mt-2">Body Text:</label>
               <textarea id="field_body" class="modal-input w-full p-2 border rounded" rows="2">${(nodeData.body || "").trim()}</textarea>
               <label class="block mb-2 mt-2">Footer:</label>
               <input type="text" id="field_footer" class="modal-input w-full p-2 border rounded" value="${(nodeData.footer || "").trim()}" />
               <label class="block mb-2 mt-2">Action Button Label:</label>
               <input type="text" id="field_actionButton" class="modal-input w-full p-2 border rounded" value="${(nodeData.actionButton || "").trim()}" />
               <p class="text-sm text-gray-600 mt-2">Sections (JSON or Array):</p>
               <textarea id="field_sections" class="modal-input w-full p-2 border rounded" rows="3">${JSON.stringify(nodeData.sections || [], null, 2).trim()}</textarea>`;
      modalFields.innerHTML = html;
      modal.style.display = "flex";
      setTimeout(() => { modalContent.classList.add("scale-100", "opacity-100"); }, 10);
    }
    else if (type === "cta") {
      html += `<label class="block mb-2">CTA Buttons (JSON or Array):</label>
               <textarea id="field_buttons" class="modal-input w-full p-2 border rounded" rows="3">${JSON.stringify(nodeData.buttons || [], null, 2).trim()}</textarea>
               <p class="text-sm text-gray-600 mt-2">E.g. [ { "id": "cta1", "title": "Click me", "url": "https://..." }, ... ]</p>`;
      modalFields.innerHTML = html;
      modal.style.display = "flex";
      setTimeout(() => { modalContent.classList.add("scale-100", "opacity-100"); }, 10);
    }
    else if (type === "start") {
      html += `<label class="block mb-2">Trigger Command:</label>
               <input type="text" id="field_triggerCommand" class="modal-input w-full p-2 border rounded" value="${(nodeData.triggerCommand || "").trim()}" />
               <label class="block mb-2 mt-2">Trigger Type:</label>
               <input type="text" id="field_triggerType" class="modal-input w-full p-2 border rounded" value="${(nodeData.triggerType || "").trim()}" />
               <label class="block mb-2 mt-2">Message:</label>
               <input type="text" id="field_message" class="modal-input w-full p-2 border rounded" value="${(nodeData.message || "").trim()}" />`;
      modalFields.innerHTML = html;
      modal.style.display = "flex";
      setTimeout(() => { modalContent.classList.add("scale-100", "opacity-100"); }, 10);
    }
    else if (type === "end") {
      html += `<label class="block mb-2">Message:</label>
               <input type="text" id="field_message" class="modal-input w-full p-2 border rounded" value="${(nodeData.message || "").trim()}" />
               <p class="text-sm text-gray-600 mt-2">End node typically just indicates the end of the flow.</p>`;
      modalFields.innerHTML = html;
      modal.style.display = "flex";
      setTimeout(() => { modalContent.classList.add("scale-100", "opacity-100"); }, 10);
    }
    else if (type === "delay") {
      html += `<label class="block mb-2">Delay (ms):</label>
               <input type="text" id="field_delay" class="modal-input w-full p-2 border rounded" value="${(nodeData.delay || "").trim()}" />`;
      modalFields.innerHTML = html;
      modal.style.display = "flex";
      setTimeout(() => { modalContent.classList.add("scale-100", "opacity-100"); }, 10);
    }
    else if (type === "image" ||type === "audio" || type === "video" || type === "document") {
      html += `<label class="block mb-2">${type.charAt(0).toUpperCase() + type.slice(1)} URL:</label>
               <input type="text" id="field_url" class="modal-input w-full p-2 border rounded" value="${(nodeData.url || "").trim()}" />`;
      if (type === "video") {
        html += `<label class="block mb-2 mt-2">Caption:</label>
                 <input type="text" id="field_caption" class="modal-input w-full p-2 border rounded" value="${(nodeData.caption || "").trim()}" />`;
      }
      if (type === "document") {
        html += `<label class="block mb-2 mt-2">Filename:</label>
                 <input type="text" id="field_filename" class="modal-input w-full p-2 border rounded" value="${(nodeData.filename || "").trim()}" />`;
      }
      modalFields.innerHTML = html;
      modal.style.display = "flex";
      setTimeout(() => { modalContent.classList.add("scale-100", "opacity-100"); }, 10);
    }
    else if (type === "location") {
      html += `<label class="block mb-2">Latitude:</label>
               <input type="text" id="field_latitude" class="modal-input w-full p-2 border rounded" value="${(nodeData.latitude || "0.0").trim()}" />
               <label class="block mb-2 mt-2">Longitude:</label>
               <input type="text" id="field_longitude" class="modal-input w-full p-2 border rounded" value="${(nodeData.longitude || "0.0").trim()}" />
               <label class="block mb-2 mt-2">Name:</label>
               <input type="text" id="field_name" class="modal-input w-full p-2 border rounded" value="${(nodeData.name || "").trim()}" />
               <label class="block mb-2 mt-2">Address:</label>
               <input type="text" id="field_address" class="modal-input w-full p-2 border rounded" value="${(nodeData.address || "").trim()}" />`;
      modalFields.innerHTML = html;
      modal.style.display = "flex";
      setTimeout(() => { modalContent.classList.add("scale-100", "opacity-100"); }, 10);
    }
    else if (type === "api") {
      html += `<label class="block mb-2">Endpoint:</label>
               <input type="text" id="field_endpoint" class="modal-input w-full p-2 border rounded" value="${(nodeData.endpoint || "https://api.example.com").trim()}" />
               <label class="block mb-2 mt-2">Method:</label>
               <input type="text" id="field_method" class="modal-input w-full p-2 border rounded" value="${(nodeData.method || "GET").trim()}" />
               <label class="block mb-2 mt-2">Payload (JSON):</label>
               <textarea id="field_payload" class="modal-input w-full p-2 border rounded" rows="3">${(nodeData.payload || "{}").trim()}</textarea>`;
      modalFields.innerHTML = html;
      modal.style.display = "flex";
      setTimeout(() => { modalContent.classList.add("scale-100", "opacity-100"); }, 10);
    }
    else {
      html += `<label class="block mb-2">Content:</label>
               <input type="text" id="field_content" class="modal-input w-full p-2 border rounded" value="${(nodeData.content || "").trim()}" />
               <p class="text-sm text-gray-600 mt-2">Unhandled node type: ${type}</p>`;
      modalFields.innerHTML = html;
      modal.style.display = "flex";
      setTimeout(() => { modalContent.classList.add("scale-100", "opacity-100"); }, 10);
    }
  }

  function closeModal() {
    modal.style.display = "none";
    modalContent.classList.remove("scale-100", "opacity-100");
    selectedNode = null;
  }

  /*** SAVE MODAL HANDLER ***/
document.getElementById("modal-save").addEventListener("click", () => {
  if (!selectedNode) return;
  const nodeId = selectedNode.getAttribute("data-id");
  const type = selectedNode.getAttribute("data-type");
  let data = nodesData[nodeId].data;

  if (type === "condition") {
    const condRows = document.querySelectorAll("#condition-rows .border");
    const newConditions = [];
    condRows.forEach(row => {
      const fieldEl = row.querySelector(".condition-field");
      const operatorEl = row.querySelector(".condition-operator");
      const valueEl = row.querySelector(".condition-value");
      const logicEl = row.querySelector(".condition-logic");
      if (fieldEl && operatorEl && valueEl && logicEl) {
        const field = fieldEl.value.trim();
        const operator = operatorEl.value.trim();
        const value = valueEl.value.trim();
        const logic = logicEl.value.trim();
        if (field || value) {
          newConditions.push({ field, operator, value, logic });
        }
      }
    });
    data.conditions = newConditions;
    data.outcomes = [{ label: "True" }, { label: "False" }];
    const scriptField = document.getElementById("field_script");
    if (scriptField) data.script = scriptField.value.trim();

    // Remove connections that are out of range for condition node (more than 2 branches)
    connections = connections.filter(conn => {
      if (conn.from === nodeId && conn.branch >= 2) {
        conn.pathEl.remove();
        return false;
      }
      return true;
    });
    selectedNode.querySelectorAll(".branch-output").forEach(el => el.remove());
    const newOutcomeHTML = data.outcomes.map((outcome, idx) => {
      return `<div class="branch-output" data-branch-index="${idx}" title="Outcome: ${outcome.label}"
                style="position:absolute; right:0; top:${20 + idx * 24}px; width:12px; height:12px; background:green; border-radius:50%; cursor:pointer;"></div>`;
    }).join("");
    selectedNode.insertAdjacentHTML("beforeend", newOutcomeHTML);
    selectedNode.querySelectorAll(".branch-output").forEach(output => {
      output.addEventListener("mousedown", (e) => {
        e.stopPropagation();
        connectionStart = {
          node: selectedNode,
          point: output,
          branchIndex: output.getAttribute("data-branch-index")
        };
        document.addEventListener("mousemove", drawTemporaryConnection);
      });
    });
  } else if (type === "template") {
    const tplEl = document.getElementById("field_template_id");
    if (tplEl) {
      data.template_id = tplEl.value;
      data.script = document.getElementById("field_script").value.trim();
      const match = ALL_TEMPLATES.find(t => t.id == data.template_id);
      if (match) {
        data.template_name = match.template_name;
        data.language = match.language;
      } else {
        data.template_name = "";
        data.language = "en_US";
      }
    }
  } else {
    // Always update the optional script field if present.
    const scriptEl = document.getElementById("field_script");
    if (scriptEl) data.script = scriptEl.value.trim();

    if (type === "start") {
      const cmdEl = document.getElementById("field_triggerCommand");
      const typeEl = document.getElementById("field_triggerType");
      if (cmdEl) data.triggerCommand = cmdEl.value.trim();
      if (typeEl) data.triggerType = typeEl.value.trim();
    }
    if (type === "text") {
      const textEl = document.getElementById("field_text");
      if (textEl) data.text = textEl.value.trim();
    } else if (type === "image") {
      const urlEl = document.getElementById("field_url");
      const captionEl = document.getElementById("field_caption");
      if (urlEl) data.url = urlEl.value.trim();
      if (captionEl) data.caption = captionEl.value.trim();
    } else if (type === "audio" || type === "video" || type === "document") {
      // New branch for media types:
      const urlEl = document.getElementById("field_url");
      if (urlEl) data.url = urlEl.value.trim();
      if (type === "video") {
        const captionEl = document.getElementById("field_caption");
        if (captionEl) data.caption = captionEl.value.trim();
      }
      if (type === "document") {
        const filenameEl = document.getElementById("field_filename");
        if (filenameEl) data.filename = filenameEl.value.trim();
      }
    } else if (type === "delay") {
      const delayEl = document.getElementById("field_delay");
      if (delayEl) data.delay = delayEl.value.trim();
    } else {
      const contentEl = document.getElementById("field_content");
      if (contentEl) data.content = contentEl.value.trim();
    }
  }

  // Re-render node content so that changes appear immediately.
  const innerContent = getNodeInnerContent(type, data);
  const nodeContent = selectedNode.querySelector(".node-content");
  if (nodeContent) {
    nodeContent.innerHTML = innerContent;
  }

  closeModal();
  pushUndoState();
  updateConnections();
});

  document.getElementById("modal-cancel").addEventListener("click", closeModal);
  document.getElementById("modal-delete").addEventListener("click", () => {
    if (selectedNode) {
      deleteNode(selectedNode);
      closeModal();
      pushUndoState();
    }
  });

  /*** EXPORT/IMPORT FLOW ***/
  function openExportModal() {
    exportModal.style.display = "flex";
    setTimeout(() => { exportContent.classList.add("scale-100", "opacity-100"); }, 10);
  }
  function closeExportModal() {
    exportModal.style.display = "none";
    exportContent.classList.remove("scale-100", "opacity-100");
  }
  document.getElementById("import-flow").addEventListener("click", () => {
    try {
      const flow = JSON.parse(exportText.value);
      loadFlow(flow);
      closeExportModal();
      pushUndoState();
    } catch (err) {
      alert("Invalid JSON!");
    }
  });
  document.getElementById("load-flow").addEventListener("click", openExportModal);
  document.getElementById("export-close") && document.getElementById("export-close").addEventListener("click", closeExportModal);
  document.getElementById("export-save").addEventListener("click", () => {
    document.getElementById("save-flow").click();
  });

  /*** SHOW FLOW DATA BUTTON ***/
  document.getElementById("show-flow").addEventListener("click", () => {
    const flow = getCurrentFlowState();
    exportText.value = JSON.stringify(flow, null, 2);
    exportHeader.textContent = "Flow Data";
    openExportModal();
  });

  /*** LOAD FLOW FUNCTION ***/
  function loadFlow(flow) {
    builder.querySelectorAll(".node").forEach(node => node.remove());
    connections = [];
    svgContainer.innerHTML = "";
    nodesData = {};
    flow.nodes.forEach(n => {
      addNode(n.type, n.position, { ...n.data, id: n.id });
      nodesData[n.id] = { id: n.id, type: n.type, position: n.position, data: n.data };
      const nodeEl = builder.querySelector(`[data-id="${n.id}"]`);
      if (nodeEl) {
        nodeEl.style.left = n.position.x + "px";
        nodeEl.style.top  = n.position.y + "px";
        const summaryHTML = `<div class="node-summary">${getNodeSummary(n.type, n.data)}</div>`;
        const nodeContent = nodeEl.querySelector(".node-content");
        if (nodeContent) {
          nodeContent.innerHTML = summaryHTML;
        }
      }
    });
    flow.connections.forEach(conn => {
      const fromNode = builder.querySelector(`[data-id="${conn.from}"]`);
      const toNode = builder.querySelector(`[data-id="${conn.to}"]`);
      if (fromNode && toNode) {
        createConnection(fromNode, toNode, conn.branch);
        let c = connections.find(c => c.from === conn.from && c.to === conn.to && c.branch === conn.branch);
        if (c) {
          c.label = conn.label;
        }
      }
    });
    updateConnections();
  }

  /*** ADDITIONAL FEATURES ***/
  document.getElementById("undo").addEventListener("click", undo);
  document.getElementById("redo").addEventListener("click", redo);

  builder.addEventListener("click", (e) => {
    const node = e.target.closest(".node");
    if (node && e.shiftKey) {
      if (selectedNodes.has(node)) {
        selectedNodes.delete(node);
        node.style.outline = "";
      } else {
        selectedNodes.add(node);
        node.style.outline = "2px dashed red";
      }
    }
  });

  document.getElementById("group-selected").addEventListener("click", () => {
    if (selectedNodes.size > 1) {
      const group = document.createElement("div");
      group.className = "group-container absolute border-2 border-dashed purple-500";
      let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
      selectedNodes.forEach(node => {
        const rect = node.getBoundingClientRect();
        const builderRect = builder.getBoundingClientRect();
        const x = rect.left - builderRect.left;
        const y = rect.top - builderRect.top;
        minX = Math.min(minX, x);
        minY = Math.min(minY, y);
        maxX = Math.max(maxX, x + rect.width);
        maxY = Math.max(maxY, y + rect.height);
      });
      group.style.left = minX + "px";
      group.style.top = minY + "px";
      group.style.width = (maxX - minX) + "px";
      group.style.height = (maxY - minY) + "px";
      builder.appendChild(group);
      selectedNodes.forEach(node => { node.style.outline = ""; });
      selectedNodes.clear();
      pushUndoState();
    } else {
      alert("Select at least 2 nodes with Shift+Click to group.");
    }
  });

  document.getElementById("preview").addEventListener("click", () => {
    previewMode = !previewMode;
    builder.querySelectorAll(".node").forEach(node => {
      node.style.pointerEvents = previewMode ? "none" : "auto";
      node.style.opacity = previewMode ? "0.7" : "1";
    });
  });

  document.getElementById("export-png").addEventListener("click", () => {
    if (typeof html2canvas !== "undefined") {
      html2canvas(builder).then(canvas => {
        const link = document.createElement("a");
        link.download = "flow.png";
        link.href = canvas.toDataURL();
        link.click();
      });
    } else {
      alert("html2canvas library not loaded.");
    }
  });

  document.getElementById("save-flow").addEventListener("click", () => {
    const flow = getCurrentFlowState();
    const startNodes = flow.nodes.filter(n => n.type === "start");
    const endNodes = flow.nodes.filter(n => n.type === "end");
    if (startNodes.length !== 1) {
      alert("Flow must contain exactly one start node.");
      return;
    }
    if (endNodes.length !== 1) {
      alert("Flow must contain exactly one end node.");
      return;
    }
    if (flow.connections.some(conn => conn.to === startNodes[0].id)) {
      alert("Start node should not have any incoming connections.");
      return;
    }
    if (flow.connections.some(conn => conn.from === endNodes[0].id)) {
      alert("End node should not have any outgoing connections.");
      return;
    }
    let errors = [];
    flow.nodes.forEach(node => {
      if (node.type !== "start") {
        const hasInput = flow.connections.some(conn => conn.to === node.id);
        if (!hasInput) {
          errors.push(`Node [${node.id}] of type ${node.type} has no incoming connection.`);
        }
      }
      if (node.type !== "end") {
        const hasOutput = flow.connections.some(conn => conn.from === node.id);
        if (!hasOutput) {
          errors.push(`Node [${node.id}] of type ${node.type} has no outgoing connection.`);
        }
      }
    });
    if (flow.connections.length === 0) {
      errors.push("Flow must contain at least one connection between the start and end nodes.");
    }
    if (errors.length > 0) {
      alert("Flow validation failed:\n" + errors.join("\n"));
      return;
    }
    flowDataInput.value = JSON.stringify(flow, null, 2);
    form.submit();
  });

  if (flowDataInput && flowDataInput.value) {
    try {
      const savedFlow = JSON.parse(flowDataInput.value);
      loadFlow(savedFlow);
    } catch (error) {
      console.error("Error parsing saved flow data:", error);
    }
  }
})();
</script>
</body>
</html>

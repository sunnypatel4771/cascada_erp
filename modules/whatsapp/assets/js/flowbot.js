$(document).ready(function () {
    class WhatsAppFlowBuilder {
        constructor() {
            this.builder = $("#builder");
            this.selectedNode = null;
            this.nodes = new Map();
            this.connections = [];
            this.draggedComponent = null;
            this.isDragging = false; // Fixed initialization issue

            this.initEventListeners();
        }

        initEventListeners() {
            $(".component").on("dragstart", this.handleDragStart.bind(this));
            this.builder.on("dragover", this.handleDragOver.bind(this));
            this.builder.on("drop", this.handleDrop.bind(this));
            $(".save-button").on("click", this.saveFlow.bind(this));

            $(document).on("dblclick", ".node", (e) => this.openEditModal($(e.currentTarget)));
        }

        handleDragStart(event) {
            this.draggedComponent = $(event.target).data("type");
        }

        handleDragOver(event) {
            event.preventDefault();
        }

        handleDrop(event) {
            event.preventDefault();
            if (this.draggedComponent) {
                const position = { x: event.clientX, y: event.clientY };
                this.addNode(this.draggedComponent, position);
                this.draggedComponent = null;
            }
        }

        addNode(type, position) {
            const nodeId = `node-${Date.now()}`;
            const node = $(`<div class="node bg-white shadow-lg p-4 border cursor-pointer relative">
                                <strong class="text-blue-600">${type.toUpperCase()}</strong>
                                <p>Double-click to edit...</p>
                            </div>`)
                .attr("data-id", nodeId)
                .css({
                    left: `${position.x - this.builder.offset().left - 75}px`,
                    top: `${position.y - this.builder.offset().top - 20}px`,
                })
                .draggable({
                    containment: "#builder",
                    start: this.initiateDrag.bind(this), // Fixed scope issue
                    drag: this.dragNode.bind(this),
                });

            this.builder.append(node);
            this.nodes.set(nodeId, node);
        }

        initiateDrag(event, ui) {
            this.isDragging = true; // Fixed: Properly initialized dragging state
        }

        dragNode(event, ui) {
            const nodeId = $(event.target).attr("data-id");
            const node = this.nodes.get(nodeId);
            if (node) {
                const position = ui.position;
                this.updateConnections(nodeId, position); // Update connections dynamically
            }
        }

        updateConnections(nodeId, position) {
            // Example logic for updating connections
            this.connections.forEach((connection) => {
                if (connection.from === nodeId || connection.to === nodeId) {
                    const line = $(`line[data-connection="${connection.id}"]`);
                    if (line.length) {
                        const startX = position.left + 75; // Adjust to center of node
                        const startY = position.top + 20;
                        const endX = 200; // Example fixed endpoint
                        const endY = 200;

                        line.attr({
                            x1: startX,
                            y1: startY,
                            x2: endX,
                            y2: endY,
                        });
                    }
                }
            });
        }

        openEditModal(node) {
            this.selectedNode = node;

            $("#dynamic-modal").remove(); // Remove existing modal
            const modal = $(`
                <div id="dynamic-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex justify-center items-center z-50">
                    <div class="bg-white p-6 rounded shadow-lg w-96 relative">
                        <h3 class="text-lg font-semibold mb-4">Edit Node</h3>
                        <button id="close-edit-modal" class="absolute top-2 right-2 text-gray-600 text-lg">✖</button>
                        <div id="modal-fields-container"></div>
                        <div class="flex justify-end space-x-2 mt-4">
                            <button id="cancel-edit-modal" class="bg-gray-500 text-white px-4 py-2 rounded">Cancel</button>
                            <button id="save-edit-modal" class="bg-blue-500 text-white px-4 py-2 rounded">Save</button>
                        </div>
                    </div>
                </div>
            `);

            $("body").append(modal);

            const fields = ["Header", "Body", "Footer"]; // Example fields
            fields.forEach((field) => {
                const inputField = `
                    <label class="block mb-2">
                        ${field}:
                        <input type="text" class="modal-input w-full p-2 border rounded" data-field="${field.toLowerCase()}">
                    </label>`;
                modal.find("#modal-fields-container").append(inputField);
            });

            // Populate with existing data if available
            const data = node.data("whatsapp") || {};
            $(".modal-input").each(function () {
                const fieldKey = $(this).data("field");
                $(this).val(data[fieldKey] || "");
            });

            $("#close-edit-modal, #cancel-edit-modal").on("click", () => modal.remove());
            $("#save-edit-modal").on("click", () => this.saveNodeChanges(modal));
        }

        saveNodeChanges(modal) {
            if (this.selectedNode) {
                const data = {};
                $(".modal-input").each(function () {
                    const fieldKey = $(this).data("field");
                    data[fieldKey] = $(this).val();
                });

                this.selectedNode.data("whatsapp", data);
                this.selectedNode.find("p").text(data.body || "Double-click to edit...");
                modal.remove();
            }
        }

        saveFlow() {
            const flowData = {
                nodes: Array.from(this.nodes.values()).map((node) => ({
                    id: node.attr("data-id"),
                    type: node.find("strong").text(),
                    whatsappData: node.data("whatsapp") || {},
                    position: {
                        x: node.css("left"),
                        y: node.css("top"),
                    },
                })),
            };

            localStorage.setItem("whatsappFlow", JSON.stringify(flowData));
            alert("Flow saved successfully!");
        }
    }

    new WhatsAppFlowBuilder();
});

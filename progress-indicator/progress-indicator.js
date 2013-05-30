/**
 * Progress Indicator
 * Requires jQuery
 */
(function($) {
	$.widget("com-devbyday.progressIndicator", {
		"version": "1.0",
		"_isComplete": false,
		"_wrapper": $("<div class=\"progress-indicator\"></div>"),
		"_progressbar": $("<div class=\"progress-bar\"></div>"),
		"_currentjob" : $("<div class=\"current-task\"></div>"),
		"_completiontext": $("<div class=\"completion-text\"></div>"),
		"_warnings": $("<div class=\"warnings\"></div>"),
		"_overlay": $("<div class=\"progress-indicator-overlay\"></div>"),
		"_listeners": {},
		"_style": $("<style type=\"text/css\">\
			body.overlay-scroll-lock {\
				overflow: hidden;\
			}\
			.progress-indicator {\
				margin: auto;\
				width: 75%;\
				min-width: 200px;\
				top: 50px;\
				position: relative;\
				border-radius: 5px;\
				overflow: hidden;\
				padding-bottom: 5px;\
			}\
				.progress-indicator button.close-modal {\
					position: absolute;\
					top: 5px;\
					right: 5px;\
					height: 20px;\
					width: 20px;\
					background-color: #990000;\
					background-image: linear-gradient(to bottom, #FF0000, #990000);\
					display: block;\
					text-align: center;\
					border: 2px outset #FF0000;\
					border-sizing: border-box;\
					border-radius: 5px;\
					padding:0;\
					color:#FFFFFF;\
				}\
				.progress-indicator .progress-status-wrapper {\
					margin: 10px;\
				}\
				.progress-indicator .progress-bar-wrapper {\
					width: 100%;\
					border: thin solid #666666;\
					border-radius: 5px;\
					background-color: #666666;\
					background-image: linear-gradient(to bottom, #CCCCCC, #999999);\
				}\
					.progress-indicator .progress-bar-wrapper .progress-bar {\
						height: 20px;\
						border-radius: 5px;\
						background-color: #009900;\
						background-image: linear-gradient(to bottom, #00FF00, #009900);\
					}\
					.progress-indicator.error-state .progress-bar-wrapper .progress-bar {\
						background-color: #990000;\
						background-image: linear-gradient(to bottom, #FF0000, #990000);\
					}\
				.progress-indicator .progress-bar-modal-header {\
					min-height: 30px;\
					font-size: 14px;\
					line-height: 28px;\
					color: #FFFFFF;\
					width: 100%;\
					border-radius: 5px 5px 0 0;\
					background-color: #21759b;\
					background-image: linear-gradient(to bottom,#2a95c5,#21759b);\
				}\
					.progress-indicator .progress-bar-modal-header .current-task {\
						margin-left: 5px;\
					}\
				.progress-indicator .completion-text {\
					min-height: 1em;\
					font-size: 1em;\
					line-height: 1em;\
					margin-top: 3px;\
					margin-bottom: 5px;\
				}\
				.progress-indicator button.toggle-warnings {\
					display: block;\
				}\
				.progress-indicator .warnings {\
					width: 100%;\
					margin: inherit auto;\
					border: thin solid #000000;\
					height: 100px;\
					overflow-y: scroll;\
					padding-left: 3px;\
				}\
				.progress-indicator .warnings > p {\
					margin: 0 0 3px 0;\
				}\
				.progress-indicator .warnings > p.error {\
					color: #FF0000;\
					font-weight: bold;\
				}\
			.progress-indicator-overlay {\
				z-index: 100000;\
				background-color: rgba(128,128,128,0.5);\
			}\
		</style>"),
		"_create": function() {
			var me = this;
			this._listeners.close = [];
			$("head").prepend(this._style);
			this._wrapper
				.append(
					$("<header class=\"progress-bar-modal-header\"></header>")
						.append(this._currentjob)
						.append($("<button class=\"close-modal\">X</button>").click(function() {
							me.close();
						}))
				)
				.append(
					$("<div class=\"progress-status-wrapper\"></div>").append(
						$("<div class=\"progress-bar-wrapper\"></div>").append(this._progressbar)
					).append(this._completiontext)
					.append($("<button class=\"toggle-warnings\">Toggle Warnings</button>").click(function() {
						me._warnings.toggle();
						})
					).append(this._warnings.hide())
				);
			this._overlay.append(this._wrapper).hide();
			this.element.append(this._overlay);
			this._wrapper.css({"background-color": "white" });
		},
		"_destroy": function() {
			this._style.remove();
			this._wrapper.remove();
		},
		"_blockUnload": function(e) {
			return "Navigating away from the page will stop processing this task. Continue?";
		},
		"updateProgress": function(amount) {
			amount = parseFloat(amount) * 100;
			if (amount >= 100) {
				amount = 100;
				this._isComplete = true;
			}
			this._progressbar.css({"width": amount+"%" });
		},
		"setTask": function(taskText) {
			
			this._currentjob.html(taskText);
		},
		"setCompletionText": function(completionText) {
			this._completiontext.html(completionText);
		},
		"setErrorState": function(errorMarkup) {
			this._wrapper.addClass("error-state");
			if (typeof errorMarkup !== "undefined") {
				this.addWarning(errorMarkup, true);
			}
			this._warnings.show();
		},
		"unsetErrorState": function() {
			this._wrapper.removeClass("error-state");
		},
		"clearWarnings": function() {
			this._warnings.html("");
		},
		"addWarning": function(warningMarkup, isError) {
			var $paragraph;
			if (typeof warningMarkup !== "undefined" && warningMarkup.length > 0) {
				$paragraph = $("<p>" + warningMarkup + "</p>");
				if (typeof isError !== "undefined" && isError) {
					$paragraph.addClass("error");
				}
				this._warnings.append($paragraph);
			}
		},
		"activate": function(taskText) {
			var me = this;
			this.clearWarnings();
			if (typeof taskText !== "undefined") {
				this.setTask(taskText);
			}
			this.updateProgress(0);
			this._overlay.css({
				"position": "fixed",
				"top": "0px",
				"left": "0px",
				"width": $(window).width(),
				"height": $(window).height(),
			}).appendTo("body").show();
			$("body").addClass("overlay-scroll-lock");
			$(window).on("resize orientationchange", function() {
				if (me._overlay.is(":visible")) {
					me._overlay.css({
						"width": $(window).width(),
						"height": $(window).height()
					});
				}
			}).on("beforeunload", this._blockUnload);
		},
		"onClose": function(handler) {
			this._listeners.close.push(handler);
		},
		"close": function() {
			console.log(this._progressbar.css("width"));
			if (this._isComplete || this._wrapper.hasClass("error-state") || confirm("This will cancel your current task. Continue?")) {
				for (var i in this._listeners.close) {
					(this._listeners.close[i])();
				}
				this._overlay.hide().appendTo(this._element);
				$("body").removeClass("overlay-scroll-lock");
				$(window).off("beforeunload", this._blockUnload);
			}
		},
	});
})(jQuery);
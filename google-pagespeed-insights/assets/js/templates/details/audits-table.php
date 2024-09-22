<script type="text/html" id="tmpl-audits-table">
	<h3>{{{data.name}}} <span class="toggle"></span><span class="right">{{data.displayValue}}</span></h3>
	<div class="details">
		<p class="description">
			{{{data.description}}}
		</p>
		<table class="audits">
			<thead>
				<tr>
					<# var keys = []; #>
					<# for (i = 0; i < data.details.headings.length; i++ ) { #>
						<th class="{{data.details.headings[ i ].key}}">
							<# if ( typeof data.details.headings[ i ].text != 'undefined' ) { #>
								{{data.details.headings[ i ].text}}
							<# } #>
						</th>	
						<#
							if ( '' == data.details.headings[ i ].key && 'subItemsHeading' in data.details.headings[ i ] ) {
								keys.push( data.details.headings[ i ].subItemsHeading );
							} else {
								keys.push( data.details.headings[ i ].key );
							}
						#>
					<# } #>
				</tr>
			</thead>
			<tbody>
				<# for (i = 0; i < data.details.items.length; i++ ) { #>
					<tr>
						<# for (x = 0; x < keys.length; x++ ) {
							if ( 'object' == typeof data.details.items[ i ][keys[ x ]] ) {

								if ( 'url' == keys[ x ] ) {
									#>
									<td class="{{keys[ x ]}}">{{{data.details.items[ i ][keys[ x ]].value}}}</td>
									<#
								} else if ( 'node' == keys[ x ] ) {
									#>
									<td class="{{keys[ x ]}}">
										{{{data.details.items[ i ][keys[ x ]]['nodeLabel']}}}
										<br>
										<code>{{{data.details.items[ i ][keys[ x ]]['snippet']}}}</code>
										<#
											if ( 'subItems' in data.details.items[ i ] && 'items' in data.details.items[ i ].subItems ) {
												for (y = 0; y < data.details.items[ i ].subItems.items.length; y++ ) {
													if ( 'failureReason' in data.details.items[ i ].subItems.items[ y ] ) {
														#>
														<br><br>
														{{{data.details.items[ i ].subItems.items[ y ].failureReason}}}
														<#
													}
												}
											}
										#>
									</td>
									<#
								} else if ( 'source' == keys[ x ] ) {
									if ( 'source-location' == data.details.items[ i ][keys[ x ]]['type'] ) {
										#>
										<td class="{{keys[ x ]}}">
											{{{data.details.items[ i ][keys[ x ]]['url']}}}
											<code>{{{data.details.items[ i ][keys[ x ]]['line']}}}:{{{data.details.items[ i ][keys[ x ]]['column']}}}</code>
										</td>
										<#
									}
								} else if ( 'entity' == keys[ x ] ) {
									#>
									<td class="{{keys[ x ]}}">
										{{{data.details.items[ i ][keys[ x ] ].text}}}
										<#
											if ( 'url' in data.details.items[ i ][keys[ x ] ] ) {
												#>
												({{{data.details.items[ i ][keys[ x ] ].url}}})
												<#
											}
										#>
									</td>
									<#
								} else {
									#>
									<td class="{{keys[ x ]}}">{{data.details.items[ i ][keys[ x ]].value}}</td>
									<#
								}
							} else {
								if ( 'url' == keys[ x ] ) {
									#>
									<td class="{{keys[ x ]}}">{{{data.details.items[ i ][keys[ x ]]}}}</td>
									<#
								} else if ( 'object' == typeof keys[ x ] && data.details.items[ i ].subItems ) {
									for (y = 0; y < data.details.items[ i ].subItems.items.length; y++ ) {
										#>
										<td class="{{keys[ x ]}}">{{{data.details.items[ i ].subItems.items[ y ][ keys[ x ].key ]}}}</td>
										<#
									}
								} else {
									#>
									<td class="{{keys[ x ]}}">{{data.details.items[ i ][keys[ x ]]}}</td>
									<#
								}
							}
						} #>
					</tr>
				<# } #>
			</tbody>
		</table>
	</div>
</script>
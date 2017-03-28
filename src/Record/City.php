<?php
namespace GeotWP\Record;


class City extends AbstractRecord {
	/**
	 * @return string
	 */
	public function zip(){
		return isset( $this->data->zip ) ? $this->data->zip : '';
	}
}